<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>
<template>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
        <p class="muted">
            {{
                $t('pa_pagination', {
                    page: pagination.current_page,
                    pages: pagination.last_page,
                    total: pagination.total,
                })
            }}
        </p>
        <div class="flex gap-4">
            <Link v-if="pagination.prev_page_url" :href="pagination.prev_page_url" class="font-medium text-brand">{{
                $t('previous')
            }}</Link
            ><Link v-if="pagination.next_page_url" :href="pagination.next_page_url" class="font-medium text-brand">{{
                $t('next')
            }}</Link>
        </div>
    </div>
</template>
