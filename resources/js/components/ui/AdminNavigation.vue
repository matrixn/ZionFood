<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { adminGroups, adminActive } from '@/lib/adminNavigation';
defineEmits<{ navigate: [] }>();
const page = usePage();
</script>
<template>
    <nav :aria-label="$t('pa_navigation')" class="admin-navigation">
        <Link
            href="/admin"
            :class="{ selected: adminActive(page.url, '/admin') }"
            :aria-current="adminActive(page.url, '/admin') ? 'page' : undefined"
            @click="$emit('navigate')"
            ><i class="pi pi-objects-column" aria-hidden="true" />{{ $t('pa_dashboard') }}</Link
        >
        <section v-for="group in adminGroups" :key="group.key">
            <h2>{{ $t(group.key) }}</h2>
            <Link
                v-for="item in group.items"
                :key="item.href"
                :href="item.href"
                :class="{ selected: adminActive(page.url, item.href) }"
                :aria-current="adminActive(page.url, item.href) ? 'page' : undefined"
                @click="$emit('navigate')"
                ><i :class="['pi', item.icon]" aria-hidden="true" />{{ $t(item.key) }}</Link
            >
        </section>
    </nav>
</template>
