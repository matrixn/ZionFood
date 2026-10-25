<script setup lang="ts">
import { onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from 'primevue/button';
const props = defineProps<{
    invitationId: string;
    invitation: {
        organization: string;
        role: string;
        expires_at: string;
        accepted_at: string | null;
        revoked_at: string | null;
    } | null;
}>();
const page = usePage();
const form = useForm({ token: '' });
onMounted(() => {
    const incoming = new URLSearchParams(location.hash.slice(1)).get('token');
    if (incoming) {
        sessionStorage.setItem('zf-invitation-' + props.invitationId, incoming);
        history.replaceState(null, '', location.pathname);
    }
    form.token = sessionStorage.getItem('zf-invitation-' + props.invitationId) ?? '';
});
function clearToken() {
    sessionStorage.removeItem('zf-invitation-' + props.invitationId);
}
</script>
<template>
    <AuthLayout
        ><Head :title="$t('accept_invitation')" />
        <h1 class="mb-5 text-3xl font-semibold">{{ $t('youre_invited') }}</h1>
        <template v-if="invitation"
            ><p class="muted mb-5">{{ invitation.organization }} · {{ $t('role_' + invitation.role) }}</p>
            <p v-if="invitation.revoked_at || new Date(invitation.expires_at) < new Date()" role="alert">
                {{ $t('this_invitation_has_expired_or_was_revoked') }}
            </p>
            <form v-else @submit.prevent="form.post('/invitations/' + invitationId, { onSuccess: clearToken })">
                <p v-for="(message, key) in form.errors" :key="key" role="alert" class="mb-4 text-red-600">
                    {{ message }}
                </p>
                <p v-if="!form.token" class="muted mb-4">
                    {{ $t('open_the_complete_invitation_link_from_your_email_in_this_browser') }}
                </p>
                <Button
                    :label="$t('accept_invitation')"
                    type="submit"
                    :loading="form.processing"
                    :disabled="!form.token"
                /></form></template
        ><template v-else
            ><p class="muted mb-6">
                {{ $t('sign_in_or_create_an_account_with_the_invited_email_address_then_verify_your_email') }}
            </p>
            <div class="flex gap-5">
                <Link
                    v-if="!(page.props.auth as { user: unknown })?.user"
                    href="/login"
                    class="font-medium text-brand"
                    >{{ $t('sign_in') }}</Link
                ><Link
                    v-if="!(page.props.auth as { user: unknown })?.user"
                    href="/register"
                    class="font-medium text-brand"
                    >{{ $t('create_account') }}</Link
                ><Link v-else href="/email/verify" class="font-medium text-brand">{{ $t('verify_email') }}</Link>
            </div></template
        ></AuthLayout
    >
</template>
