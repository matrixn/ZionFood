import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ApiError, api } from '@/lib/http';
import type { MenuContent } from '@/types';
export interface MenuNotification {
    public_id: string;
    read_at: string | null;
    created_at: string;
    draft: string;
    location: string;
    item_name: string;
    status: string;
    kind: string;
}
export interface ContentReview {
    public_id: string;
    item_name: string;
    item: string;
    location: string;
    kind: string;
    status: string;
    proposal: Partial<MenuContent> & { description?: string };
    report: { notes?: string; approved_sections?: string[] } | null;
    error_code: string | null;
    source_current: boolean;
    can_write: boolean;
}
export const useMenuNotifications = defineStore('menu-notifications', () => {
    const records = ref<MenuNotification[]>([]);
    const review = ref<ContentReview | null>(null);
    const visible = ref(false);
    const busy = ref(false);
    const error = ref('');
    const reviewed = ref(0);
    let polling = false;
    let recipient: number | null = null;
    let generation = 0;
    let opening = 0;
    function identify(user: number | null) {
        if (recipient === user) return;
        recipient = user;
        generation++;
        opening++;
        busy.value = false;
        records.value = [];
        review.value = null;
        visible.value = false;
        error.value = '';
    }
    async function poll() {
        if (polling) return;
        polling = true;
        const version = generation;
        try {
            const result = await api<{ notifications: MenuNotification[] }>('/app/notifications');
            if (version === generation) records.value = result.notifications;
        } catch (e) {
            if (version === generation && e instanceof ApiError && [401, 403, 419].includes(e.status)) {
                records.value = [];
                review.value = null;
                visible.value = false;
            }
            throw e;
        } finally {
            polling = false;
        }
    }
    async function read(id: string) {
        await api(`/app/notifications/${id}/read`, 'POST');
        const record = records.value.find((n) => n.public_id === id);
        if (record) record.read_at = new Date().toISOString();
    }
    async function open(location: string, draft: string, notification?: string) {
        const request = ++opening;
        visible.value = true;
        busy.value = true;
        error.value = '';
        review.value = null;
        try {
            const result = await api<ContentReview>(`/app/locations/${location}/menu-content/drafts/${draft}`);
            if (request !== opening) return;
            review.value = result;
            if (notification) await read(notification);
        } catch (e) {
            if (request === opening) error.value = (e as Error).message;
        } finally {
            if (request === opening) busy.value = false;
        }
    }
    async function decide(action: 'approve' | 'reject', sections: string[]) {
        const target = review.value;
        if (!target || busy.value) return;
        const request = opening;
        busy.value = true;
        error.value = '';
        try {
            await api(`/app/locations/${target.location}/menu-content/drafts/${target.public_id}`, 'POST', {
                action,
                ...(action === 'approve'
                    ? {
                          sections,
                          changes: Object.fromEntries(
                              Object.entries(target.proposal ?? {}).filter(([key]) => key !== 'nutrition'),
                          ),
                      }
                    : {}),
            });
            if (request !== opening) return;
            target.status = action === 'approve' ? 'approved' : 'rejected';
            reviewed.value++;
            visible.value = false;
            // Publication has succeeded even if the subsequent inbox refresh is offline.
            void poll().catch(() => {});
        } catch (e) {
            if (request === opening) error.value = (e as Error).message;
        } finally {
            if (request === opening) busy.value = false;
        }
    }
    return { identify, records, review, visible, busy, error, reviewed, poll, read, open, decide };
});
