<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Toast, { type ToastMessageOptions } from 'primevue/toast';
import Checkbox from 'primevue/checkbox';
import Textarea from 'primevue/textarea';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import { useToast } from 'primevue/usetoast';
import { useMenuNotifications } from '@/lib/menuNotifications';
import { getEcho } from '@/lib/echo';
import { tr as t, localeTag } from '@/i18n';
import { allergenCodes } from '@/lib/foodLabels';
const store = useMenuNotifications();
const toast = useToast();
const page = usePage();
const inbox = ref(false);
const sections = ref<string[]>([]);
const unread = computed(() => store.records.filter((n) => !n.read_at));
const seen = new Set<string>();
const number = (value: number | null | undefined) =>
    value == null ? '—' : new Intl.NumberFormat(localeTag(), { maximumFractionDigits: 3 }).format(value);
const sectionLabel = (key: string) =>
    t(
        (
            {
                nutrition: 'mc_nutrition',
                allergens: 'mc_allergens',
                may_contain: 'mc_may_contain',
                labels: 'mc_labels',
                description: 'mc_description',
                translations: 'mc_translations',
            } as Record<string, string>
        )[key] ?? key,
    );
const allergenOptions = computed(() =>
    allergenCodes.map((code) => ({ code, name: t('allergen_group_' + (allergenCodes.indexOf(code) + 1)) })),
);
const labelOptions = computed(() =>
    ['vegetarian', 'vegan', 'gluten_free', 'halal', 'nut_free', 'dairy_free', 'hot', 'raw'].map((code) => ({
        code,
        name: t('mc_label_' + code),
    })),
);
const editable = computed(() => store.review?.status === 'ready' && store.review.can_write && !store.busy);
const codeLabel = (code: string) =>
    allergenCodes.includes(code) ? t('allergen_group_' + (allergenCodes.indexOf(code) + 1)) : t('mc_label_' + code);
watch(
    () => store.review,
    (review) => {
        sections.value = Object.keys(review?.proposal ?? {});
    },
);
watch(
    () => page.props.flash as { success?: string },
    (flash) => {
        const message = flash?.success;
        if (message) toast.add({ severity: 'success', summary: message, life: 5000 });
    },
    { immediate: true },
);
watch(
    () => store.records,
    (records) => {
        const fresh = records.filter((n) => !n.read_at && !seen.has(n.public_id));
        records.forEach((n) => seen.add(n.public_id));
        fresh.slice(0, 3).forEach((n) =>
            toast.add({
                severity: n.status === 'ready' ? 'success' : 'warn',
                summary: n.item_name,
                detail: t('mc_status_' + n.status),
                life: 12000,
                data: n,
            } as ToastMessageOptions),
        );
    },
);
watch(
    () => store.reviewed,
    () => {
        toast.add({
            severity: 'success',
            summary: t(store.review?.status === 'approved' ? 'auto_published_success' : 'auto_rejected_success'),
            life: 5000,
        });
    },
);
const poll = () => {
    if (document.visibilityState === 'visible')
        void store.poll().catch(() => {
            /* Recover on the next focus or poll. */
        });
};
let timer: ReturnType<typeof setInterval>;
let channel = '';
onMounted(() => {
    store.identify((page.props.auth as { user?: { id: number } })?.user?.id ?? null);
    poll();
    timer = setInterval(poll, 5000);
    window.addEventListener('focus', poll);
    window.addEventListener('online', poll);
    document.addEventListener('visibilitychange', poll);
    const user = (page.props.auth as { user?: { id: number } })?.user;
    if (user) {
        channel = `user.${user.id}.notifications`;
        getEcho()?.private(channel).listen('.workspace.notification', poll);
    }
});
onUnmounted(() => {
    clearInterval(timer);
    window.removeEventListener('focus', poll);
    window.removeEventListener('online', poll);
    document.removeEventListener('visibilitychange', poll);
    if (channel) getEcho()?.leave(channel);
});
</script>
<template>
    <Toast position="top-right" class="!max-w-[calc(100vw-2rem)]">
        <template #message="{ message }">
            <button
                v-if="message.data"
                class="w-full text-left"
                @click="store.open(message.data.location, message.data.draft, message.data.public_id)"
            >
                <strong class="block">{{ message.summary }}</strong
                ><span class="text-sm">{{ message.detail }} · {{ $t('auto_view_result') }} →</span>
            </button>
            <div v-else>
                <strong>{{ message.summary }}</strong>
                <p v-if="message.detail">{{ message.detail }}</p>
            </div>
        </template>
    </Toast>
    <Button
        icon="pi pi-bell"
        text
        :badge="unread.length ? String(unread.length) : undefined"
        :aria-label="$t('auto_notifications')"
        @click="inbox = true"
    />
    <Dialog
        v-model:visible="inbox"
        modal
        :header="$t('auto_notifications')"
        :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
    >
        <p v-if="!store.records.length" class="muted py-8 text-center">{{ $t('auto_no_notifications') }}</p>
        <button
            v-for="n in store.records"
            :key="n.public_id"
            class="mb-2 flex w-full items-center gap-3 rounded-xl border border-slate-200 p-4 text-left hover:border-violet-300 dark:border-slate-700"
            @click="
                inbox = false;
                store.open(n.location, n.draft, n.public_id);
            "
        >
            <i
                :class="[
                    'pi',
                    n.status === 'ready' ? 'pi-check-circle text-emerald-600' : 'pi-info-circle text-amber-600',
                ]"
            /><span class="flex-1"
                ><strong class="block text-sm">{{ n.item_name }}</strong
                ><span class="muted text-xs">{{ $t('mc_status_' + n.status) }}</span></span
            ><span v-if="!n.read_at" class="h-2 w-2 rounded-full bg-brand" />
        </button>
    </Dialog>
    <Dialog
        v-model:visible="store.visible"
        modal
        :header="store.review?.item_name ?? $t('auto_result')"
        :style="{ width: '48rem', maxWidth: 'calc(100vw - 2rem)' }"
    >
        <p v-if="store.busy && !store.review" role="status" class="py-12 text-center">
            <i class="pi pi-spin pi-spinner mr-2" />{{ $t('auto_loading') }}
        </p>
        <p v-if="store.error" role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">
            {{ store.error }}
        </p>
        <template v-if="store.review">
            <p class="mb-5 text-sm text-slate-500">{{ $t('mc_status_' + store.review.status) }}</p>
            <p
                v-if="!store.review.source_current && store.review.status === 'ready'"
                class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800"
            >
                {{ $t('auto_stale') }}
            </p>
            <p v-if="store.review.error_code" class="mb-4 text-sm text-red-700">
                {{ $t('auto_failed') }} ({{ store.review.error_code }})
            </p>
            <div
                v-for="(value, key) in store.review.proposal"
                :key="key"
                class="mb-5 rounded-2xl border border-slate-200 p-5 dark:border-slate-700"
            >
                <label class="mb-4 flex items-center gap-3 font-semibold"
                    ><Checkbox
                        v-if="store.review.status === 'ready' && store.review.can_write"
                        v-model="sections"
                        :aria-label="sectionLabel(String(key))"
                        :value="key"
                        :disabled="store.busy"
                    /><span class="field-label"
                        >{{ sectionLabel(String(key)) }}<FieldHelp :label="sectionLabel(String(key))" field="" /></span
                    ><span
                        v-if="
                            store.review.status === 'approved' &&
                            store.review.report?.approved_sections &&
                            !store.review.report.approved_sections.includes(String(key))
                        "
                        class="ml-auto text-xs font-normal text-slate-400"
                        >{{ $t('auto_not_published') }}</span
                    ></label
                >
                <template v-if="key === 'nutrition' && store.review.proposal.nutrition">
                    <p
                        v-if="store.review.proposal.nutrition.estimated"
                        class="mb-4 rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-900"
                    >
                        {{ $t('auto_estimate_notice') }}
                    </p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-xs text-slate-500">
                                <th class="pb-3 text-left">{{ $t('auto_nutrient') }}</th>
                                <th>
                                    {{ $t('auto_one_portion') }} · {{ store.review.proposal.nutrition.portion_grams }} g
                                </th>
                                <th>100 g</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(amount, nutrient) in store.review.proposal.nutrition.values"
                                :key="nutrient"
                                class="border-t border-slate-100 dark:border-slate-800"
                            >
                                <td class="py-2">{{ $t('mc_nutrient_' + nutrient) }}</td>
                                <td class="text-right tabular-nums">
                                    {{ number(store.review.proposal.nutrition.per_serving?.[nutrient]) }}
                                </td>
                                <td class="text-right tabular-nums">{{ number(amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <ul class="mt-4 list-disc space-y-1 pl-4 text-xs leading-relaxed text-slate-500">
                        <li v-for="note in store.review.proposal.nutrition.assumptions" :key="note">{{ note }}</li>
                    </ul>
                </template>
                <template v-else-if="key === 'translations'"
                    ><div v-for="(row, language) in store.review.proposal.translations" :key="language" class="mb-4">
                        <p class="text-xs font-semibold uppercase text-brand">{{ language }}</p>
                        <template v-if="editable"
                            ><span class="field-control-help"
                                ><InputText
                                    v-model="row.name"
                                    :aria-label="$t('mc_translation_name')"
                                    class="mt-2 w-full" /><FieldHelp
                                    :label="$t('mc_translation_name')"
                                    field="name"
                                    kind="InputText" /></span
                            ><span class="field-control-help"
                                ><Textarea
                                    v-model="row.description"
                                    :aria-label="$t('mc_translation_description')"
                                    rows="3"
                                    class="mt-2 w-full" /><FieldHelp
                                    :label="$t('mc_translation_description')"
                                    field="description"
                                    kind="Textarea" /></span
                            ><span class="field-control-help"
                                ><Textarea
                                    v-model="row.ingredients_text"
                                    :aria-label="$t('mc_translation_ingredients_text')"
                                    rows="2"
                                    class="mt-2 w-full" /><FieldHelp
                                    :label="$t('mc_translation_ingredients_text')"
                                    field="ingredients_text"
                                    kind="Textarea" /></span></template
                        ><template v-else
                            ><strong>{{ row.name }}</strong>
                            <p class="mt-1 text-sm">{{ row.description }}</p>
                            <p class="mt-2 text-xs text-slate-500">({{ row.ingredients_text }})</p></template
                        >
                        <p class="mt-1 text-xs">
                            {{ row.portion_size }} · {{ row.category_name }} · {{ row.menu_name }}
                        </p>
                        <p v-for="(name, id) in row.variants" :key="id" class="text-sm">{{ name }}</p>
                        <div v-for="(group, id) in row.groups" :key="id" class="text-sm">
                            {{ group.name }}: {{ Object.values(group.options).join(', ') }}
                        </div>
                    </div></template
                >
                <template v-else-if="key === 'description'"
                    ><span v-if="editable" class="field-control-help"
                        ><Textarea
                            v-model="store.review.proposal.description"
                            rows="4"
                            maxlength="2000"
                            :aria-label="$t('mc_description')"
                            class="w-full" /><FieldHelp
                            :label="$t('mc_description')"
                            field="description"
                            kind="Textarea"
                    /></span>
                    <p v-else class="text-sm leading-relaxed">{{ value }}</p></template
                >
                <span v-else-if="editable" class="field-control-help"
                    ><MultiSelect
                        :model-value="value"
                        :options="key === 'labels' ? labelOptions : allergenOptions"
                        option-label="name"
                        option-value="code"
                        display="chip"
                        :aria-label="sectionLabel(String(key))"
                        class="w-full"
                        @update:model-value="
                            store.review.proposal[key as 'allergens' | 'may_contain' | 'labels'] = $event
                        " /><FieldHelp :label="sectionLabel(String(key))" field="" kind="MultiSelect"
                /></span>
                <p v-else class="text-sm leading-relaxed">
                    {{ (value as string[])?.map(codeLabel).join(', ') || $t('auto_none_suggested') }}
                </p>
            </div>
            <p v-if="store.review.report?.notes" class="text-xs leading-relaxed text-slate-500">
                {{ store.review.report.notes }}
            </p>
        </template>
        <template #footer
            ><div class="flex w-full flex-wrap items-center justify-end gap-2">
                <Button :label="$t('close')" text @click="store.visible = false" /><template
                    v-if="store.review?.status === 'ready' && store.review.can_write"
                    ><Button
                        :label="$t('auto_reject')"
                        severity="secondary"
                        :disabled="store.busy"
                        @click="store.decide('reject', [])" /><Button
                        :label="$t('auto_approve')"
                        icon="pi pi-check"
                        :loading="store.busy"
                        :disabled="store.busy || !sections.length || !store.review.source_current"
                        @click="store.decide('approve', sections)"
                /></template></div
        ></template>
    </Dialog>
</template>
