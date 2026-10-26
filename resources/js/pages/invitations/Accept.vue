<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from 'primevue/button';
import { api } from '@/lib/http';
import { tr } from '@/i18n';
const props = defineProps<{ invitationId: string }>();
const page = usePage();
const invitation = ref<{ email: string; organization: string; role: string } | null>(null);
const error = ref('');
const loading = ref(true);
const form = useForm({ token: '' });
onMounted(async () => {
    const incoming = new URLSearchParams(location.hash.slice(1)).get('token');
    if (incoming) {
        sessionStorage.setItem('zf-invitation-' + props.invitationId, incoming);
        history.replaceState(history.state, '', location.pathname);
    }
    form.token = sessionStorage.getItem('zf-invitation-' + props.invitationId) ?? '';
    try {
        if (!form.token) {
            error.value = tr('open_the_complete_invitation_link_from_your_email_in_this_browser');
            return;
        }
        invitation.value = await api(`/invitations/${props.invitationId}/prepare`, 'POST', { token: form.token });
        history.replaceState(history.state, '', location.pathname);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
});
function clearToken() {
    sessionStorage.removeItem('zf-invitation-' + props.invitationId);
}
</script>
<template>
    <AuthLayout>
        <Head :title="$t('accept_invitation')"><meta name="robots" content="noindex" /></Head>
        <h1 class="mb-5 text-3xl font-semibold">{{ $t('youre_invited') }}</h1>
        <p v-if="loading" role="status" class="muted">{{ $t('invitation_loading') }}</p>
        <p v-if="error" role="alert" class="mb-4 text-red-700">{{ error }}</p>
        <template v-if="invitation">
            <p class="muted mb-3">{{ invitation.organization }} · {{ $t('role_' + invitation.role) }}</p>
            <p class="mb-5 font-medium">{{ invitation.email }}</p>
            <form
                v-if="(page.props.auth as { user: unknown })?.user"
                @submit.prevent="form.post('/invitations/' + invitationId, { onSuccess: clearToken })"
            >
                <p v-for="(message, key) in form.errors" :key="key" role="alert" class="mb-4 text-red-600">
                    {{ message }}
                </p>
                <Button
                    :label="$t('accept_invitation')"
                    type="submit"
                    :loading="form.processing"
                    :disabled="!form.token"
                />
            </form>
            <template v-else>
                <p class="muted mb-6">{{ $t('invitation_join_hint') }}</p>
                <div class="flex gap-5">
                    <Link href="/register" class="font-medium text-brand">{{ $t('create_account') }}</Link>
                    <Link href="/login" class="font-medium text-brand">{{ $t('sign_in') }}</Link>
                </div>
            </template>
        </template>
    </AuthLayout>
</template>
