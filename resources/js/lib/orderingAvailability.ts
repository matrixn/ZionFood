import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export interface OrderingAvailability {
    schedule: {
        is_open: boolean;
        opens_at: string | null;
        closes_at: string | null;
        next_opens_at: string | null;
        checked_at: string;
        timezone: string;
    };
    can_order: boolean;
    can_add_to_cart: boolean;
    reason: string;
    guard_enabled: boolean;
    mode: 'online' | 'table';
    deadline_at: string | null;
}

export function orderingState(status: OrderingAvailability, elapsedMs = 0) {
    const now = Date.parse(status.schedule.checked_at) + Math.max(0, elapsedMs);
    const open = status.schedule.opens_at ? Date.parse(status.schedule.opens_at) : null;
    const close = status.schedule.closes_at ? Date.parse(status.schedule.closes_at) : null;
    const isOpen = open !== null && close !== null && now >= open && now < close;
    const deadline = status.deadline_at ? Date.parse(status.deadline_at) : null;
    const remaining = deadline !== null ? Math.max(0, Math.ceil((deadline - now) / 1000)) : null;
    const timeBlocked = !isOpen || remaining === 0;
    const hardReason = ['ordering_paused', 'table_closed', 'service_disabled', 'no_open_tables'].includes(
        status.reason,
    );
    const reason = timeBlocked && !hardReason ? (!isOpen ? 'outside_hours' : 'cutoff') : status.reason;
    return {
        ...status,
        isOpen,
        canOrder: status.can_order && !timeBlocked,
        canAdd: status.can_add_to_cart && (!status.guard_enabled || !timeBlocked),
        reason,
        remaining,
        lastHour: isOpen && remaining !== null && remaining > 0 && remaining <= 3600,
    };
}

export function useOrderingAvailability(initial: () => OrderingAvailability, endpoint: () => string) {
    const status = ref(initial());
    const elapsed = ref(0);
    const stale = ref(false);
    let synced = performance.now();
    let clock: ReturnType<typeof setInterval>;
    let poll: ReturnType<typeof setInterval>;
    let active: AbortController | null = null;
    let stopped = false;
    const state = computed(() => {
        const current = orderingState(status.value, elapsed.value);
        return { ...current, canOrder: !stale.value && current.canOrder, canAdd: !stale.value && current.canAdd };
    });
    watch(initial, (value) => {
        status.value = value;
        synced = performance.now();
        elapsed.value = 0;
        stale.value = false;
    });
    async function refresh() {
        if (active || stopped || document.visibilityState === 'hidden') return;
        const controller = new AbortController();
        active = controller;
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(endpoint(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Availability unavailable');
            const value: OrderingAvailability = await response.json();
            if (
                !value?.schedule ||
                !Number.isFinite(Date.parse(value.schedule.checked_at)) ||
                typeof value.can_order !== 'boolean' ||
                typeof value.can_add_to_cart !== 'boolean'
            )
                throw new Error('Invalid availability');
            if (!stopped) {
                status.value = value;
                synced = performance.now();
                elapsed.value = 0;
                stale.value = false;
            }
        } catch {
            if (!stopped) stale.value = true;
        } finally {
            clearTimeout(timeout);
            active = null;
        }
    }
    const resume = () => {
        void refresh();
    };
    onMounted(() => {
        void refresh();
        clock = setInterval(() => {
            elapsed.value = performance.now() - synced;
            if (elapsed.value > 45000) stale.value = true;
        }, 1000);
        poll = setInterval(resume, 15000);
        window.addEventListener('focus', resume);
        window.addEventListener('online', resume);
        document.addEventListener('visibilitychange', resume);
    });
    onBeforeUnmount(() => {
        stopped = true;
        active?.abort();
        clearInterval(clock);
        clearInterval(poll);
        window.removeEventListener('focus', resume);
        window.removeEventListener('online', resume);
        document.removeEventListener('visibilitychange', resume);
    });
    return { status, state, elapsed, stale, refresh };
}
