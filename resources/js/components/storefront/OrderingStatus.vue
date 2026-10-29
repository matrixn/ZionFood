<script setup lang="ts">
import { computed } from 'vue';
import { orderingState, type OrderingAvailability } from '@/lib/orderingAvailability';
import { useLocationDates } from '@/lib/locationDates';
const props = defineProps<{ availability: OrderingAvailability; elapsed?: number; stale?: boolean }>();
const state = computed(() => orderingState(props.availability, props.elapsed ?? 0));
const dates = useLocationDates(() => ({ timezone: props.availability.schedule.timezone }));
const minutes = computed(() => Math.max(1, Math.ceil((state.value.remaining ?? 0) / 60)));
</script>
<template>
    <section
        data-testid="ordering-status"
        aria-live="polite"
        aria-atomic="true"
        class="mb-6 rounded-2xl border p-4 sm:p-5"
        :class="
            stale || !state.isOpen || !state.canOrder
                ? 'border-rose-200 bg-rose-50 text-rose-950'
                : state.lastHour
                  ? 'border-amber-200 bg-amber-50 text-amber-950'
                  : 'border-emerald-200 bg-emerald-50 text-emerald-950'
        "
    >
        <div class="flex items-start gap-3">
            <i
                :class="[
                    'pi mt-1 text-xl',
                    !state.isOpen ? 'pi-moon' : state.lastHour ? 'pi-clock' : 'pi-check-circle',
                ]"
                aria-hidden="true"
            />
            <div class="min-w-0 space-y-1">
                <h2 class="font-semibold">
                    {{ $t(state.isOpen ? 'ordering_location_open' : 'ordering_location_closed') }}
                </h2>
                <p v-if="state.isOpen && availability.schedule.closes_at" class="text-sm">
                    {{ $t('ordering_closes_at', { time: dates.time(availability.schedule.closes_at) }) }}
                </p>
                <p v-else-if="availability.schedule.next_opens_at" class="text-sm">
                    {{ $t('ordering_next_open', { date: dates.dateTime(availability.schedule.next_opens_at) }) }}
                </p>
                <p v-else-if="!state.isOpen" class="text-sm">{{ $t('ordering_no_schedule') }}</p>
                <p
                    v-if="state.lastHour && state.canOrder && !stale"
                    class="text-sm font-semibold"
                    data-testid="ordering-last-hour"
                >
                    {{
                        $t('ordering_last_minutes', {
                            minutes,
                            time: dates.time(availability.deadline_at!),
                        })
                    }}
                </p>
                <p v-if="stale" class="text-sm">{{ $t('ordering_status_stale') }}</p>
                <p v-else-if="!state.canOrder" class="text-sm">{{ $t('ordering_reason_' + state.reason) }}</p>
                <p v-if="state.canAdd && !state.canOrder && !stale" class="text-sm">
                    {{ $t('ordering_outside_allowed') }}
                </p>
            </div>
        </div>
    </section>
</template>
