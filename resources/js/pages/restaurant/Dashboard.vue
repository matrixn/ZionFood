<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Select from 'primevue/select';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFStatCard from '@/components/ui/ZFStatCard.vue';
import { money } from '@/lib/http';
import { tr as t, localeTag } from '@/i18n';
import type { WorkspaceProps } from '@/types';
interface Analytics {
    days: number;
    from: string;
    to: string;
    timezone: string;
    orders: null | {
        count: number;
        completed: number;
        value: number;
        average: number;
        active: number;
        today: number;
        cancelled: number;
        series: { day: string; orders: number; value: number }[];
        top_products: { product_public_id: string; name: string; quantity: number; value: number }[];
        status: { status: string; count: number }[];
        fulfillment: { fulfillment: string; count: number }[];
    };
    menu: null | { recipes: number; available: number; categories: number; menus: number };
    reservations: null | {
        count: number;
        guests: number;
        today: number;
        upcoming: number;
        status: { status: string; count: number }[];
    };
    tables: null | {
        count: number;
        seats: number;
        manually_open: number;
        occupied: number;
        top: { table_code: string; table_area: string; orders: number; value: number }[];
        reserved?: { code: string; area: string; reservations: number; guests: number }[];
    };
    visits: null | { count: number; tracking_since: string | null; series: { day: string; visits: number }[] };
}
const props = defineProps<
    WorkspaceProps & { analytics: Analytics; subscription: { status: string; trial_ends_at: string } | null }
>();
const days = ref(props.analytics.days);
const chartMetric = ref<'orders' | 'value'>('orders');
const periodOptions = computed(() => [7, 30, 90].map((value) => ({ value, label: t('dash_days', { days: value }) })));
const cards = computed(() => [
    ...(props.analytics.orders
        ? [
              {
                  label: t('dash_orders'),
                  value: props.analytics.orders.count,
                  icon: 'pi-shopping-bag',
                  help: t('dash_orders_help'),
              },
              {
                  label: t('dash_value'),
                  value: money(props.analytics.orders.value, props.location.currency),
                  icon: 'pi-wallet',
                  help: t('dash_value_help'),
              },
              {
                  label: t('dash_average'),
                  value: money(props.analytics.orders.average, props.location.currency),
                  icon: 'pi-chart-line',
                  help: t('dash_average_help'),
              },
          ]
        : []),
    ...(props.analytics.menu
        ? [
              {
                  label: t('dash_recipes'),
                  value: props.analytics.menu.recipes,
                  icon: 'pi-book',
                  help: t('dash_recipes_help'),
              },
          ]
        : []),
    ...(props.analytics.reservations
        ? [
              {
                  label: t('dash_reservations'),
                  value: props.analytics.reservations.count,
                  icon: 'pi-calendar',
                  help: t('dash_reservations_help'),
              },
              {
                  label: t('dash_guests'),
                  value: props.analytics.reservations.guests,
                  icon: 'pi-users',
                  help: t('dash_guests_help'),
              },
          ]
        : []),
    ...(props.analytics.tables
        ? [
              {
                  label: t('dash_capacity'),
                  value: props.analytics.tables.seats,
                  icon: 'pi-building',
                  help: t('dash_capacity_help'),
              },
          ]
        : []),
    ...(props.analytics.visits
        ? [
              {
                  label: t('dash_visits'),
                  value: props.analytics.visits.count,
                  icon: 'pi-eye',
                  help: t('dash_visits_help'),
              },
          ]
        : []),
]);
const points = computed(() => {
    const rows = props.analytics.orders?.series ?? [];
    const max = Math.max(1, ...rows.map((row) => Number(row[chartMetric.value])));
    return rows.map((row, index) => ({
        x: 40 + (index * 720) / Math.max(1, rows.length - 1),
        y: 200 - (Number(row[chartMetric.value]) / max) * 165,
        row,
    }));
});
const path = computed(() => points.value.map((point, index) => `${index ? 'L' : 'M'} ${point.x} ${point.y}`).join(' '));
const area = computed(() => (points.value.length ? path.value + ` L ${points.value.at(-1)!.x} 200 L 40 200 Z` : ''));
const maxProducts = computed(() =>
    Math.max(1, ...(props.analytics.orders?.top_products.map((item) => Number(item.quantity)) ?? [])),
);
function changePeriod() {
    router.get(`/app/locations/${props.location.public_id}`, { days: days.value }, { preserveScroll: true });
}
const format = (value: number) => new Intl.NumberFormat(localeTag()).format(value);
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('overview')" />
        <div class="mx-auto max-w-[1440px] space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-brand">
                        {{ location.restaurant.name }} · {{ location.name }}
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight">{{ $t('dash_title') }}</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ analytics.from }} — {{ analytics.to }} · {{ analytics.timezone }}
                    </p>
                </div>
                <div class="field-control-help">
                    <Select
                        v-model="days"
                        :options="periodOptions"
                        option-label="label"
                        option-value="value"
                        :aria-label="$t('dash_period')"
                        @change="changePeriod"
                    /><FieldHelp :label="t('dash_period')" :text="t('dash_period_help')" />
                </div>
            </header>
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div v-for="card in cards" :key="card.label" class="relative">
                    <ZFStatCard :label="card.label" :value="card.value" :icon="card.icon" /><FieldHelp
                        :label="card.label"
                        :text="card.help"
                        class="absolute right-4 top-4"
                    />
                </div>
            </div>
            <section class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl bg-slate-950 p-5 text-white lg:col-span-2">
                    <h2 class="mb-4 text-sm font-medium text-slate-300">{{ $t('dash_now') }}</h2>
                    <div class="flex flex-wrap gap-x-8 gap-y-4">
                        <div v-if="analytics.orders">
                            <strong class="block text-3xl">{{ analytics.orders.active }}</strong
                            ><span class="text-xs text-slate-300">{{ $t('dash_active_orders') }}</span>
                        </div>
                        <div v-if="analytics.reservations">
                            <strong class="block text-3xl">{{ analytics.reservations.upcoming }}</strong
                            ><span class="text-xs text-slate-300">{{ $t('dash_upcoming') }}</span>
                        </div>
                        <div v-if="analytics.tables">
                            <strong class="block text-3xl"
                                >{{ analytics.tables.occupied }} / {{ analytics.tables.count }}</strong
                            ><span class="text-xs text-slate-300"
                                >{{ $t('dash_qr_sessions') }}
                                <FieldHelp :label="t('dash_qr_sessions')" :text="t('dash_qr_help')"
                            /></span>
                        </div>
                        <div>
                            <strong
                                class="block text-lg"
                                :class="location.accepting_orders ? 'text-emerald-300' : 'text-amber-300'"
                                >{{ location.accepting_orders ? $t('accepting_orders') : $t('orders_paused') }}</strong
                            ><span class="text-xs text-slate-300">{{ location.address }}</span>
                        </div>
                    </div>
                </div>
                <div class="panel !p-5">
                    <h2 class="mb-3 font-semibold">{{ $t('dash_today') }}</h2>
                    <p v-if="analytics.orders" class="mb-2 text-sm text-slate-500">
                        {{ $t('dash_orders') }}
                        <strong class="float-right text-ink dark:text-white">{{ analytics.orders.today }}</strong>
                    </p>
                    <p v-if="analytics.reservations" class="mb-2 text-sm text-slate-500">
                        {{ $t('dash_reservations') }}
                        <strong class="float-right text-ink dark:text-white">{{ analytics.reservations.today }}</strong>
                    </p>
                    <p v-if="analytics.menu" class="text-sm text-slate-500">
                        {{ $t('dash_available') }}
                        <strong class="float-right text-ink dark:text-white"
                            >{{ analytics.menu.available }} / {{ analytics.menu.recipes }}</strong
                        >
                    </p>
                </div>
            </section>
            <section v-if="analytics.orders" class="panel">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $t('dash_evolution') }}</h2>
                        <p class="mt-1 text-xs text-slate-400">{{ $t('dash_value_help') }}</p>
                    </div>
                    <div class="field-control-help">
                        <Select
                            v-model="chartMetric"
                            :options="[
                                { value: 'orders', label: t('dash_orders') },
                                { value: 'value', label: t('dash_value') },
                            ]"
                            option-label="label"
                            option-value="value"
                            :aria-label="$t('dash_chart_metric')"
                        /><FieldHelp :label="t('dash_chart_metric')" :text="t('dash_chart_help')" />
                    </div>
                </div>
                <svg
                    viewBox="0 0 800 210"
                    preserveAspectRatio="none"
                    role="img"
                    :aria-label="t('dash_evolution')"
                    class="w-full"
                >
                    <defs>
                        <linearGradient id="dashboard-area" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#635bff" stop-opacity=".3" />
                            <stop offset="100%" stop-color="#635bff" stop-opacity=".02" />
                        </linearGradient>
                    </defs>
                    <path
                        d="M40 35 H760 M40 117 H760 M40 200 H760"
                        stroke="currentColor"
                        class="text-slate-100 dark:text-slate-800"
                        fill="none"
                    />
                    <path :d="area" fill="url(#dashboard-area)" />
                    <path :d="path" stroke="#635bff" stroke-width="3" fill="none" stroke-linejoin="round" />
                    <circle
                        v-for="point in points"
                        :key="point.row.day"
                        :cx="point.x"
                        :cy="point.y"
                        r="3"
                        fill="#635bff"
                    >
                        <title>
                            {{ point.row.day }}:
                            {{
                                chartMetric === 'value'
                                    ? money(Number(point.row.value), location.currency)
                                    : format(Number(point.row.orders))
                            }}
                        </title>
                    </circle>
                </svg>
                <div class="mt-2 flex justify-between text-xs text-slate-400">
                    <span>{{ analytics.from }}</span
                    ><span>{{ analytics.to }}</span>
                </div>
                <details class="mt-4 text-xs text-slate-500">
                    <summary class="cursor-pointer">{{ $t('dash_daily_data') }}</summary>
                    <table class="mt-3 w-full text-left">
                        <thead>
                            <tr>
                                <th>{{ $t('dash_day') }}</th>
                                <th class="text-right">{{ $t('dash_orders') }}</th>
                                <th class="text-right">{{ $t('dash_value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in analytics.orders.series"
                                :key="row.day"
                                class="border-t border-slate-100 dark:border-slate-800"
                            >
                                <td class="py-2">{{ row.day }}</td>
                                <td class="text-right">{{ row.orders }}</td>
                                <td class="text-right">{{ money(Number(row.value), location.currency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </details>

                <p v-if="!analytics.orders.count" class="text-center text-sm text-slate-400">
                    {{ $t('dash_no_orders') }}
                </p>
            </section>
            <div class="grid gap-6 lg:grid-cols-2">
                <section v-if="analytics.orders" class="panel">
                    <div class="mb-5 flex items-center gap-2">
                        <h2 class="text-lg font-semibold">{{ $t('dash_top_products') }}</h2>
                        <FieldHelp :label="t('dash_top_products')" :text="t('dash_top_help')" />
                    </div>
                    <p v-if="!analytics.orders.top_products.length" class="muted py-6">{{ $t('dash_no_completed') }}</p>
                    <div
                        v-for="(product, index) in analytics.orders.top_products"
                        :key="product.product_public_id"
                        class="mb-5"
                    >
                        <div class="mb-2 flex justify-between gap-3 text-sm">
                            <span
                                ><span class="mr-2 text-slate-400">{{ index + 1 }}.</span>{{ product.name }}</span
                            ><strong class="shrink-0"
                                >{{ format(Number(product.quantity)) }} {{ $t('dash_portions') }}</strong
                            >
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-violet-50 dark:bg-slate-800">
                            <div
                                class="h-full rounded-full bg-brand"
                                :style="{ width: (Number(product.quantity) / maxProducts) * 100 + '%' }"
                            />
                        </div>
                        <p class="mt-1 text-right text-xs text-slate-400">
                            {{ money(Number(product.value), location.currency) }}
                        </p>
                    </div>
                </section>
                <section v-if="analytics.orders" class="panel">
                    <h2 class="mb-5 text-lg font-semibold">{{ $t('dash_order_mix') }}</h2>
                    <div v-for="row in analytics.orders.fulfillment" :key="row.fulfillment" class="mb-5">
                        <div class="mb-2 flex justify-between text-sm">
                            <span>{{
                                $t(row.fulfillment === 'dine_in' ? 'fulfillment_dine_in' : row.fulfillment)
                            }}</span
                            ><strong
                                >{{ row.count }} ·
                                {{
                                    Math.round((Number(row.count) / Math.max(1, analytics.orders.count)) * 100)
                                }}%</strong
                            >
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                class="h-2 rounded-full bg-cyan-500"
                                :style="{
                                    width: (Number(row.count) / Math.max(1, analytics.orders.count)) * 100 + '%',
                                }"
                            />
                        </div>
                    </div>
                    <div
                        class="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-5 text-sm dark:border-slate-700"
                    >
                        <span class="text-slate-500"
                            >{{ $t('dash_completed')
                            }}<strong class="ml-2 text-emerald-600">{{ analytics.orders.completed }}</strong></span
                        ><span class="text-slate-500"
                            >{{ $t('dash_cancelled')
                            }}<strong class="ml-2 text-rose-600">{{ analytics.orders.cancelled }}</strong></span
                        >
                    </div>
                </section>
                <section v-if="analytics.reservations" class="panel">
                    <h2 class="mb-4 text-lg font-semibold">{{ $t('dash_reservation_status') }}</h2>
                    <p v-if="!analytics.reservations.count" class="muted">{{ $t('dash_no_reservations') }}</p>
                    <div
                        v-for="row in analytics.reservations.status"
                        :key="row.status"
                        class="flex justify-between border-b border-slate-100 py-3 text-sm dark:border-slate-800"
                    >
                        <span>{{ $t('reservation_status_' + row.status) }}</span
                        ><strong>{{ row.count }}</strong>
                    </div>
                </section>
                <section v-if="analytics.tables" class="panel">
                    <h2 class="mb-2 text-lg font-semibold">{{ $t('dash_top_tables') }}</h2>
                    <p class="mb-4 text-xs text-slate-400">{{ $t('dash_top_tables_help') }}</p>
                    <p v-if="!analytics.tables.top.length" class="muted">{{ $t('dash_no_table_orders') }}</p>
                    <div
                        v-for="(table, index) in analytics.tables.top"
                        :key="table.table_code + table.table_area"
                        class="flex items-center justify-between gap-3 border-b border-slate-100 py-3 dark:border-slate-800"
                    >
                        <div class="text-sm">
                            <span class="mr-2 text-slate-400">{{ index + 1 }}.</span>{{ table.table_code
                            }}<span class="mt-1 block text-xs text-slate-400">{{ table.table_area }}</span>
                        </div>
                        <div class="text-right">
                            <strong class="text-sm">{{ table.orders }} {{ $t('dash_orders_short') }}</strong>
                            <p class="text-xs text-slate-400">{{ money(Number(table.value), location.currency) }}</p>
                        </div>
                    </div>
                    <div v-if="analytics.tables.reserved?.length" class="mt-6">
                        <h3 class="mb-3 text-sm font-semibold">{{ $t('dash_top_reserved') }}</h3>
                        <div
                            v-for="table in analytics.tables.reserved"
                            :key="table.code + table.area"
                            class="flex justify-between py-2 text-sm"
                        >
                            <span>{{ table.code }} · {{ table.area }}</span
                            ><strong>{{ table.reservations }}</strong>
                        </div>
                    </div>
                </section>
                <section v-if="analytics.visits" class="panel">
                    <div class="mb-2 flex items-center gap-2">
                        <h2 class="text-lg font-semibold">{{ $t('dash_menu_reach') }}</h2>
                        <FieldHelp :label="t('dash_menu_reach')" :text="t('dash_visits_help')" />
                    </div>
                    <strong class="block py-3 text-4xl font-semibold text-brand">{{
                        format(analytics.visits.count)
                    }}</strong>
                    <p class="text-sm leading-relaxed text-slate-500">{{ $t('dash_visits_help') }}</p>
                    <p class="mt-3 text-xs text-slate-400">
                        {{
                            analytics.visits.tracking_since
                                ? $t('dash_tracking_since', { date: analytics.visits.tracking_since })
                                : $t('dash_tracking_new')
                        }}
                    </p>
                    <div
                        v-if="analytics.menu"
                        class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 text-sm dark:border-slate-700"
                    >
                        <p>
                            {{ $t('dash_categories') }} <strong>{{ analytics.menu.categories }}</strong>
                        </p>
                        <p>
                            {{ $t('dash_menus') }} <strong>{{ analytics.menu.menus }}</strong>
                        </p>
                    </div>
                </section>
                <section class="panel">
                    <h2 class="mb-4 text-lg font-semibold">{{ $t('dash_shortcuts') }}</h2>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            v-if="permissions.orders !== 'none'"
                            :href="'/app/locations/' + location.public_id + '/orders'"
                            class="rounded-xl bg-violet-50 px-4 py-3 text-sm text-brand"
                            >{{ $t('orders') }} →</Link
                        ><Link
                            v-if="permissions.reservations !== 'none'"
                            :href="'/app/locations/' + location.public_id + '/reservations'"
                            class="rounded-xl bg-violet-50 px-4 py-3 text-sm text-brand"
                            >{{ $t('booking_manage') }} →</Link
                        ><Link
                            v-if="permissions.tables !== 'none'"
                            :href="'/app/locations/' + location.public_id + '/tables'"
                            class="rounded-xl bg-violet-50 px-4 py-3 text-sm text-brand"
                            >{{ $t('dining_tables') }} →</Link
                        ><Link
                            v-if="permissions.kitchen !== 'none'"
                            :href="'/app/locations/' + location.public_id + '/kitchen'"
                            class="rounded-xl bg-violet-50 px-4 py-3 text-sm text-brand"
                            >{{ $t('open_kitchen') }} →</Link
                        >
                    </div>
                    <p v-if="subscription" class="muted mt-5">
                        {{ $t('plan') }} {{ $t('subscription_' + subscription.status) }}
                    </p>
                </section>
            </div>
        </div>
    </RestaurantLayout>
</template>
