<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { tr as t } from '@/i18n';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import type { WorkspaceProps } from '@/types';

interface Table {
    code: string;
    area: string;
    seats: number;
}
interface Profile {
    basics?: {
        restaurant_name: string;
        name: string;
        address: string;
        city: string;
        postal_code: string;
        country: string;
        phone: string;
        email: string;
        website: string;
        cuisine: string;
        timezone: string;
        latitude: number | null;
        longitude: number | null;
    };
    seating?: { mode: string; capacity: number; tables: Table[] };
    services?: { dine_in_planned: boolean; scheduled_orders_planned: boolean; catering_planned?: boolean };
    reservations?: {
        planned: boolean;
        confirmation: string;
        duration_minutes: number;
        interval_minutes: number;
        minimum_notice_minutes: number;
        advance_days: number;
        min_party_size: number;
        max_party_size: number;
        cancellation_hours: number;
        guest_notes: string;
    };
    legal?: {
        company_name: string;
        company_address: string;
        tax_id: string;
        registration_number: string;
        terms_url: string;
        privacy_url: string;
        cancellation_policy: string;
    };
}
const props = defineProps<
    WorkspaceProps & {
        step: string;
        profile: Profile;
        revision: number;
        progress: {
            steps: Record<string, boolean>;
            blockers: string[];
            ready_to_publish: boolean;
            completed: number;
            total: number;
        };
    }
>();
const base = computed(() => `/app/locations/${props.location.public_id}`);
const steps = [
    'basics',
    'seating',
    'services',
    'hours',
    'menu',
    'branding',
    'reservations',
    'staff',
    'legal',
    'publish',
    'payments',
];
const groups = [
    { title: 'setup_group_place', steps: ['basics', 'seating'] },
    { title: 'setup_group_operations', steps: ['services', 'hours', 'menu', 'branding'] },
    { title: 'setup_group_guests', steps: ['reservations', 'staff', 'legal'] },
    { title: 'setup_group_launch', steps: ['publish', 'payments'] },
];
const basics = useForm({
    revision: props.revision,
    restaurant_name: props.location.restaurant.name,
    name: props.location.name,
    address: props.location.address,
    city: '',
    postal_code: '',
    country: 'RO',
    phone: '',
    email: '',
    website: '',
    cuisine: '',
    timezone: props.location.timezone,
    latitude: null as number | null,
    longitude: null as number | null,
    ...props.profile.basics,
});
Object.assign(basics, {
    restaurant_name: props.location.restaurant.name,
    name: props.location.name,
    address: props.location.address,
    timezone: props.location.timezone,
});
basics.defaults();
const seating = useForm({
    revision: props.revision,
    mode: 'capacity',
    capacity: 0,
    tables: [] as Table[],
    ...props.profile.seating,
});
const services = useForm({
    revision: props.revision,
    pickup_enabled: props.location.fulfillment.pickup_enabled,
    delivery_enabled: props.location.fulfillment.delivery_enabled,
    dine_in_planned: props.profile.services?.dine_in_planned ?? false,
    scheduled_orders_planned: props.profile.services?.scheduled_orders_planned ?? false,
    catering_planned: props.profile.services?.catering_planned ?? false,
});
const reservations = useForm({
    revision: props.revision,
    planned: false,
    confirmation: 'manual',
    duration_minutes: 90,
    interval_minutes: 30,
    minimum_notice_minutes: 60,
    advance_days: 30,
    min_party_size: 1,
    max_party_size: 8,
    cancellation_hours: 24,
    guest_notes: '',
    ...props.profile.reservations,
});
const legal = useForm({
    revision: props.revision,
    company_name: '',
    company_address: '',
    tax_id: '',
    registration_number: '',
    terms_url: '',
    privacy_url: '',
    cancellation_policy: '',
    ...props.profile.legal,
});
const publish = useForm({ revision: props.revision, accepting_orders: props.location.accepting_orders });
interface WizardForm {
    revision: number;
    isDirty: boolean;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    clearErrors: () => void;
    defaults: () => unknown;
    put: (url: string, options: { preserveScroll: boolean; onSuccess: () => void }) => void;
}
const forms: Record<string, WizardForm> = { basics, seating, services, reservations, legal, publish };
const currentForm = computed(() => forms[props.step as keyof typeof forms]);
const countries = [
    { label: 'România', value: 'RO' },
    { label: 'Republica Moldova', value: 'MD' },
    { label: 'United Kingdom', value: 'GB' },
    { label: 'Deutschland', value: 'DE' },
    { label: 'France', value: 'FR' },
    { label: 'Italia', value: 'IT' },
    { label: 'España', value: 'ES' },
];
const zones = [
    'Europe/Bucharest',
    'Europe/Chisinau',
    'Europe/London',
    'Europe/Berlin',
    'Europe/Paris',
    'Europe/Rome',
    'Europe/Madrid',
];
const options = (values: string[]) => values.map((value) => ({ label: t(`setup_${value}`), value }));
const totalSeats = computed(() =>
    seating.mode === 'tables' ? seating.tables.reduce((sum, table) => sum + (table.seats || 0), 0) : seating.capacity,
);
const next = computed(() => steps[steps.indexOf(props.step) + 1]);
const previous = computed(() => steps[steps.indexOf(props.step) - 1]);
const editors: Record<string, string> = { hours: '/settings', menu: '/menu', branding: '/branding', staff: '/staff' };
const external = computed(() => editors[props.step]);
watch(
    () => props.revision,
    (revision) => {
        Object.values(forms).forEach((form) => {
            form.revision = revision;
        });
    },
);
watch(
    () => props.step,
    () => {
        Object.values(forms).forEach((form) => form.clearErrors());
    },
);
function save() {
    currentForm.value?.put(`${base.value}/setup/${props.step}`, {
        preserveScroll: true,
        onSuccess: () => {
            currentForm.value?.defaults();
        },
    });
}
onBeforeUnmount(
    router.on('before', (event) => {
        if (event.detail.visit.method === 'get' && currentForm.value?.isDirty && !window.confirm(t('setup_unsaved')))
            event.preventDefault();
    }),
);
</script>

<template>
    <RestaurantLayout v-bind="$props">
        <Head :title="$t('setup_title')" />
        <ZFPageHeader :title="$t('setup_title')" :description="$t('setup_description')">
            <span class="rounded-full bg-violet-50 px-4 py-2 text-sm text-brand"
                >{{ progress.completed }}/{{ progress.total }} {{ $t('setup_completed') }}</span
            >
        </ZFPageHeader>
        <Select
            :model-value="step"
            :options="steps.map((key) => ({ label: $t(`setup_step_${key}`), value: key }))"
            option-label="label"
            option-value="value"
            :aria-label="$t('setup_steps')"
            class="mb-5 w-full xl:!hidden"
            @update:model-value="router.visit(`${base}/setup/${$event}`)"
        />
        <div class="grid items-start gap-6 xl:grid-cols-[250px_minmax(0,1fr)]">
            <aside class="panel hidden space-y-6 xl:block" :aria-label="$t('setup_steps')">
                <div
                    class="h-1.5 overflow-hidden rounded bg-slate-100"
                    role="progressbar"
                    :aria-valuenow="progress.completed"
                    :aria-valuemin="0"
                    :aria-valuemax="progress.total"
                    :aria-label="$t('setup_completed')"
                >
                    <div
                        class="h-full bg-brand"
                        :style="{ width: `${(progress.completed / progress.total) * 100}%` }"
                    />
                </div>
                <nav v-for="group in groups" :key="group.title" class="grid gap-1">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        {{ $t(group.title) }}
                    </h2>
                    <Link
                        v-for="key in group.steps"
                        :key="key"
                        :href="`${base}/setup/${key}`"
                        :aria-current="step === key ? 'step' : undefined"
                        :class="[
                            'flex items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm',
                            step === key
                                ? 'bg-violet-50 font-semibold text-brand dark:bg-violet-950'
                                : 'text-slate-600 dark:text-slate-300',
                        ]"
                    >
                        <span>{{ $t(`setup_step_${key}`) }}</span
                        ><i v-if="progress.steps[key]" class="pi pi-check text-emerald-600" aria-hidden="true" /><span
                            v-if="key === 'payments'"
                            class="text-xs"
                            >{{ $t('setup_soon') }}</span
                        >
                    </Link>
                </nav>
            </aside>
            <section class="min-w-0 space-y-5">
                <div class="panel">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-brand">
                        {{ $t('setup_your_restaurant') }}
                    </p>
                    <h2 class="text-2xl font-semibold">{{ $t(`setup_step_${step}`) }}</h2>
                    <p class="muted mt-2">{{ $t(`setup_hint_${step}`) }}</p>
                </div>
                <form v-if="currentForm" class="setup-form panel space-y-6" @submit.prevent="save">
                    <div
                        v-if="Object.keys(currentForm.errors).length"
                        role="alert"
                        class="rounded-xl bg-red-50 p-4 text-red-700"
                    >
                        <p v-for="(message, key) in currentForm.errors" :key="key">{{ message }}</p>
                    </div>
                    <div v-if="step === 'basics'" class="grid gap-5 md:grid-cols-2">
                        <label class="field"
                            >{{ $t('restaurant_name')
                            }}<InputText v-model="basics.restaurant_name" required maxlength="150"
                        /></label>
                        <label class="field"
                            >{{ $t('location_name') }}<InputText v-model="basics.name" required maxlength="150"
                        /></label>
                        <label class="field md:col-span-2"
                            >{{ $t('street_address') }}<InputText v-model="basics.address" required maxlength="255"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_city') }}<InputText v-model="basics.city" required maxlength="100"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_postal') }}<InputText v-model="basics.postal_code" maxlength="20"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_country')
                            }}<Select
                                v-model="basics.country"
                                :options="countries"
                                option-label="label"
                                option-value="value"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_timezone') }}<Select v-model="basics.timezone" :options="zones" editable
                        /></label>
                        <label class="field"
                            >{{ $t('setup_phone')
                            }}<InputText v-model="basics.phone" type="tel" required maxlength="35"
                        /></label>
                        <label class="field"
                            >{{ $t('email') }}<InputText v-model="basics.email" type="email" maxlength="254"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_website') }}<InputText v-model="basics.website" type="url" maxlength="255"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_cuisine') }}<InputText v-model="basics.cuisine" maxlength="150"
                        /></label>
                        <details class="md:col-span-2">
                            <summary class="cursor-pointer text-sm font-medium">{{ $t('setup_map_optional') }}</summary>
                            <div class="mt-4 grid gap-5 md:grid-cols-2">
                                <label class="field"
                                    >{{ $t('setup_latitude')
                                    }}<InputNumber
                                        v-model="basics.latitude"
                                        :min="-90"
                                        :max="90"
                                        :max-fraction-digits="7" /></label
                                ><label class="field"
                                    >{{ $t('setup_longitude')
                                    }}<InputNumber
                                        v-model="basics.longitude"
                                        :min="-180"
                                        :max="180"
                                        :max-fraction-digits="7"
                                /></label>
                            </div>
                        </details>
                    </div>
                    <div v-else-if="step === 'seating'" class="space-y-5">
                        <label class="field"
                            >{{ $t('setup_seating_mode')
                            }}<Select
                                v-model="seating.mode"
                                :aria-label="$t('setup_seating_mode')"
                                :options="options(['capacity', 'tables'])"
                                option-label="label"
                                option-value="value"
                        /></label>
                        <label v-if="seating.mode === 'capacity'" class="field"
                            >{{ $t('setup_capacity')
                            }}<InputNumber v-model="seating.capacity" fluid :min="0" :max="10000"
                        /></label>
                        <div v-else class="space-y-4">
                            <fieldset
                                v-for="(table, index) in seating.tables"
                                :key="index"
                                class="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_90px_40px]"
                            >
                                <legend class="px-2 text-sm">{{ $t('setup_table') }} {{ index + 1 }}</legend>
                                <label class="field"
                                    >{{ $t('setup_table_code')
                                    }}<InputText v-model="table.code" required maxlength="30"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_area') }}<InputText v-model="table.area" required maxlength="80"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_seats')
                                    }}<InputNumber v-model="table.seats" fluid :min="1" :max="100"
                                /></label>
                                <Button
                                    type="button"
                                    icon="pi pi-trash"
                                    text
                                    severity="danger"
                                    :aria-label="$t('setup_remove_table')"
                                    @click="seating.tables.splice(index, 1)"
                                />
                            </fieldset>
                            <Button
                                type="button"
                                outlined
                                icon="pi pi-plus"
                                :label="$t('setup_add_table')"
                                @click="
                                    seating.tables.push({
                                        code: `T${seating.tables.length + 1}`,
                                        area: t('setup_main_room'),
                                        seats: 4,
                                    })
                                "
                            />
                        </div>
                        <p class="rounded-xl bg-violet-50 p-4 font-semibold text-brand">
                            {{ $t('setup_total_seats') }}: {{ totalSeats }}
                        </p>
                    </div>
                    <div v-else-if="step === 'services'" class="space-y-5">
                        <label class="flex items-center justify-between gap-4"
                            ><span>{{ $t('setup_pickup_live') }}</span
                            ><ToggleSwitch v-model="services.pickup_enabled"
                        /></label>
                        <label class="flex items-center justify-between gap-4"
                            ><span>{{ $t('setup_delivery_live') }}</span
                            ><ToggleSwitch v-model="services.delivery_enabled"
                        /></label>
                        <Link
                            v-if="services.delivery_enabled"
                            :href="base + '/settings'"
                            class="inline-block font-medium text-brand"
                            >{{ $t('setup_delivery_zones') }} →</Link
                        >
                        <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                            {{ $t('setup_planning_only') }}
                        </div>
                        <label class="flex items-center justify-between gap-4"
                            ><span>{{ $t('setup_dine_in_plan') }}</span
                            ><ToggleSwitch v-model="services.dine_in_planned"
                        /></label>
                        <label class="flex items-center justify-between gap-4"
                            ><span>{{ $t('setup_scheduled_plan') }}</span
                            ><ToggleSwitch v-model="services.scheduled_orders_planned"
                        /></label>
                        <label class="flex items-center justify-between gap-4"
                            ><span>{{ $t('setup_catering_plan') }}</span
                            ><ToggleSwitch v-model="services.catering_planned" :aria-label="$t('setup_catering_plan')"
                        /></label>
                    </div>
                    <div v-else-if="step === 'reservations'" class="space-y-5">
                        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                            {{ $t('setup_reservation_notice') }}
                        </p>
                        <label class="flex items-center justify-between gap-4"
                            ><span>{{ $t('setup_reservations_plan') }}</span
                            ><ToggleSwitch v-model="reservations.planned" :aria-label="$t('setup_reservations_plan')"
                        /></label>
                        <template v-if="reservations.planned">
                            <label class="field"
                                >{{ $t('setup_confirmation')
                                }}<Select
                                    v-model="reservations.confirmation"
                                    :options="options(['manual', 'automatic'])"
                                    option-label="label"
                                    option-value="value"
                            /></label>
                            <div class="grid gap-5 md:grid-cols-2">
                                <label class="field"
                                    >{{ $t('setup_duration')
                                    }}<InputNumber v-model="reservations.duration_minutes" fluid :min="15" :max="480"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_interval')
                                    }}<Select v-model="reservations.interval_minutes" :options="[15, 30, 60]"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_notice_minutes')
                                    }}<InputNumber
                                        v-model="reservations.minimum_notice_minutes"
                                        fluid
                                        :min="0"
                                        :max="10080"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_advance_days')
                                    }}<InputNumber v-model="reservations.advance_days" fluid :min="1" :max="365"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_min_guests')
                                    }}<InputNumber v-model="reservations.min_party_size" fluid :min="1" :max="100"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_max_guests')
                                    }}<InputNumber
                                        v-model="reservations.max_party_size"
                                        :min="reservations.min_party_size"
                                        :max="100"
                                /></label>
                                <label class="field"
                                    >{{ $t('setup_cancel_hours')
                                    }}<InputNumber v-model="reservations.cancellation_hours" fluid :min="0" :max="720"
                                /></label>
                            </div>
                            <label class="field"
                                >{{ $t('setup_guest_notes')
                                }}<Textarea v-model="reservations.guest_notes" rows="3" maxlength="1500"
                            /></label>
                        </template>
                    </div>
                    <div v-else-if="step === 'legal'" class="grid gap-5 md:grid-cols-2">
                        <label class="field"
                            >{{ $t('setup_company_name')
                            }}<InputText v-model="legal.company_name" required maxlength="200"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_company_address')
                            }}<InputText v-model="legal.company_address" required maxlength="255"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_tax_id') }}<InputText v-model="legal.tax_id" maxlength="40"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_registration')
                            }}<InputText v-model="legal.registration_number" maxlength="40"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_terms_url') }}<InputText v-model="legal.terms_url" type="url" maxlength="255"
                        /></label>
                        <label class="field"
                            >{{ $t('setup_privacy_url')
                            }}<InputText v-model="legal.privacy_url" type="url" maxlength="255"
                        /></label>
                        <label class="field md:col-span-2"
                            >{{ $t('setup_cancel_policy')
                            }}<Textarea v-model="legal.cancellation_policy" rows="4" maxlength="3000"
                        /></label>
                    </div>
                    <div v-else-if="step === 'publish'" class="space-y-5">
                        <div v-if="progress.blockers.length" class="rounded-xl bg-amber-50 p-4 text-amber-900">
                            <p class="font-semibold">{{ $t('setup_complete_first') }}</p>
                            <ul class="mt-2 list-inside list-disc">
                                <li v-for="blocker in progress.blockers" :key="blocker">
                                    <Link
                                        :href="`${base}/setup/${blocker === 'delivery_zones' ? 'services' : blocker}`"
                                        >{{ $t(`setup_step_${blocker}`) }}</Link
                                    >
                                </li>
                            </ul>
                        </div>
                        <label class="flex items-center justify-between gap-4"
                            ><span>{{ $t('setup_accept_orders') }}</span
                            ><ToggleSwitch
                                v-model="publish.accepting_orders"
                                :disabled="!progress.ready_to_publish && !location.accepting_orders"
                        /></label>
                        <a
                            :href="`/store/${location.public_id}`"
                            target="_blank"
                            rel="noopener"
                            class="inline-block font-semibold text-brand"
                            >{{ $t('setup_preview') }} →</a
                        >
                        <p class="muted text-sm">{{ $t('setup_publish_scope') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-5">
                        <span class="text-sm text-slate-500">{{ $t('setup_save_hint') }}</span>
                        <Button
                            type="submit"
                            :label="$t('setup_save')"
                            icon="pi pi-check"
                            :loading="currentForm.processing"
                        />
                    </div>
                </form>
                <div v-else-if="external" class="panel space-y-5">
                    <p class="muted">{{ $t('setup_existing_editor') }}</p>
                    <Link
                        :href="base + external"
                        class="inline-block rounded-xl bg-brand px-5 py-3 font-semibold text-white"
                        >{{ $t('setup_open_editor') }} →</Link
                    >
                    <p class="text-sm text-slate-500">{{ $t('setup_return_hint') }}</p>
                </div>
                <div v-else-if="step === 'payments'" class="panel space-y-4">
                    <i class="pi pi-wallet text-4xl text-brand" aria-hidden="true" />
                    <h3 class="text-xl font-semibold">{{ $t('setup_payments_later') }}</h3>
                    <p class="muted">{{ $t('setup_payments_notice') }}</p>
                </div>
                <div class="flex justify-between gap-3">
                    <Link
                        v-if="previous"
                        :href="`${base}/setup/${previous}`"
                        class="rounded-lg px-4 py-3 text-sm text-slate-600"
                        >← {{ $t('previous') }}</Link
                    ><span v-else />
                    <Link
                        v-if="next"
                        :href="`${base}/setup/${next}`"
                        class="rounded-lg px-4 py-3 text-sm font-semibold text-brand"
                        >{{ $t('next') }} →</Link
                    >
                </div>
            </section>
        </div>
    </RestaurantLayout>
</template>

<style scoped>
.setup-form .field {
    min-width: 0;
}
.setup-form :deep(.p-inputtext) {
    width: 100%;
    min-width: 0;
}
.setup-form :deep(.p-inputnumber) {
    min-width: 0;
    max-width: 100%;
}
</style>
