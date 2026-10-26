<script setup lang="ts">
import { useSetupEditorGuard } from '@/lib/useSetupEditorGuard';
import { tr as t } from '@/i18n';

import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFConfirmDialog from '@/components/ui/ZFConfirmDialog.vue';
import { useConfirm } from 'primevue/useconfirm';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import type { WorkspaceProps } from '@/types';
interface Member {
    id: number;
    role: string;
    user_id: number;
    user: { name: string; email: string };
    location_ids: number[];
}
interface Invitation {
    public_id: string;
    email: string;
    role: string;
    expires_at: string;
    revoked_at: string | null;
}
const props = defineProps<WorkspaceProps & { actorRole: string; members: Member[]; invitations: Invitation[] }>();
const confirm = useConfirm();
const editing = ref<Member | null>(null);
const visible = ref(false);
const roles = computed(() => [
    { label: t('staff'), value: 'staff' },
    ...(props.actorRole === 'owner' ? [{ label: t('manager'), value: 'manager' }] : []),
]);
const invite = useForm({ email: '', role: 'staff', location_ids: [props.location.id] as number[] });
const edit = useForm({ role: 'staff', location_ids: [] as number[] });
const base = `/app/locations/${props.location.public_id}/staff`;
function editable(member: Member) {
    return member.role !== 'owner' && (props.actorRole === 'owner' || member.role === 'staff');
}
function open(member: Member) {
    editing.value = member;
    edit.role = member.role;
    edit.location_ids = [...member.location_ids];
    edit.clearErrors();
    visible.value = true;
}
function remove(member: Member) {
    confirm.require({
        header: t('remove_access'),
        message: t('remove_this_person_from_the_organization_their_other_organizations_are_unaffected'),
        accept: () => router.delete(`${base}/${member.id}`, { preserveScroll: true }),
    });
}
function revoke(invitation: Invitation) {
    confirm.require({
        header: t('revoke_invitation'),
        message: t('revoke_this_invitation'),
        accept: () => router.delete(`${base}/invitations/${invitation.public_id}`, { preserveScroll: true }),
    });
}
useSetupEditorGuard(
    () => !!props.embedded,
    () => invite.isDirty || (visible.value && edit.isDirty),
);
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head v-if="!embedded" :title="$t('staff')" /><ZFPageHeader
            :title="$t('your_team_connected')"
            :description="$t('invite_colleagues_and_control_their_access_to_restaurant_locations')" />
        <section class="panel mb-6">
            <h2 class="mb-4 text-xl font-semibold">{{ $t('invite_a_colleague') }}</h2>
            <p class="muted mb-5">
                {{ $t('the_invitation_expires_in_seven_days_the_recipient_must_verify_the_invited_email_address') }}
            </p>
            <form
                class="grid items-end gap-4"
                :class="embedded ? 'md:grid-cols-2' : 'lg:grid-cols-4'"
                @submit.prevent="
                    invite.post(base + '/invitations', {
                        onSuccess: () => {
                            invite.reset('email');
                            invite.defaults();
                        },
                        preserveScroll: true,
                    })
                "
            >
                <label class="field"
                    >{{ $t('email')
                    }}<InputText v-model="invite.email" type="email" required autocomplete="off" /></label
                ><label class="field"
                    >{{ $t('role')
                    }}<Select v-model="invite.role" :options="roles" option-label="label" option-value="value" /></label
                ><label class="field"
                    >{{ $t('assigned_locations')
                    }}<MultiSelect
                        v-model="invite.location_ids"
                        :options="locations"
                        option-label="name"
                        option-value="id"
                        :disabled="invite.role === 'manager'" /></label
                ><Button :label="$t('send_invitation')" type="submit" :loading="invite.processing" />
            </form>
            <p v-for="(message, key) in invite.errors" :key="key" role="alert" class="mt-3 text-red-600">
                {{ message }}
            </p>
            <p class="muted mt-4">
                {{ $t('managers_can_operate_all_organization_locations_staff_can_only_operate_assigned_locations') }}
            </p>
        </section>
        <section class="panel mb-6">
            <h2 class="mb-5 text-xl font-semibold">{{ $t('organization_members') }}</h2>
            <div
                v-for="member in members"
                :key="member.id"
                class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 py-4"
            >
                <div>
                    <strong>{{ member.user.name }}</strong>
                    <p class="muted">{{ member.user.email }}</p>
                    <p class="mt-2 text-sm">
                        <span class="font-medium">{{ $t('role_' + member.role) }}</span> ·
                        {{
                            member.role === 'staff'
                                ? locations
                                      .filter((l) => member.location_ids.includes(l.id))
                                      .map((l) => l.name)
                                      .join(', ')
                                : $t('all_locations')
                        }}
                    </p>
                </div>
                <div v-if="editable(member)" class="flex gap-2">
                    <Button :label="$t('edit_access')" outlined @click="open(member)" /><Button
                        :label="$t('remove_access')"
                        severity="danger"
                        text
                        @click="remove(member)"
                    />
                </div>
                <p v-else class="muted">
                    {{ member.role === 'owner' ? $t('protected_owner') : $t('owner_managed_role') }}
                </p>
            </div>
        </section>
        <section class="panel">
            <h2 class="mb-4 text-xl font-semibold">{{ $t('invitations') }}</h2>
            <p v-if="!invitations.length" class="muted">{{ $t('no_pending_invitations') }}</p>
            <div
                v-for="invitation in invitations"
                :key="invitation.public_id"
                data-testid="invitation-row"
                class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 py-4"
            >
                <div>
                    <strong>{{ invitation.email }}</strong>
                    <p class="muted">
                        {{ $t('role_' + invitation.role) }} · {{ $t('expires_on') }}
                        {{
                            new Date(invitation.expires_at).toLocaleDateString(
                                $i18n.locale === 'ro' ? 'ro-RO' : 'en-GB',
                            )
                        }}
                    </p>
                </div>
                <span v-if="invitation.revoked_at" class="muted">{{ $t('revoked') }}</span
                ><Button
                    v-else-if="actorRole === 'owner' || invitation.role === 'staff'"
                    :label="$t('revoke_invitation')"
                    text
                    severity="danger"
                    @click="revoke(invitation)"
                />
            </div>
        </section>
        <Dialog v-model:visible="visible" :header="$t('edit_staff_access')" modal class="w-full max-w-lg"
            ><form
                class="grid gap-5"
                @submit.prevent="
                    edit.put(`${base}/${editing?.id}`, { onSuccess: () => (visible = false), preserveScroll: true })
                "
            >
                <p class="muted">{{ editing?.user.email }}</p>
                <label class="field"
                    >{{ $t('role')
                    }}<Select v-model="edit.role" :options="roles" option-label="label" option-value="value" /></label
                ><label class="field"
                    >{{ $t('assigned_locations')
                    }}<MultiSelect
                        v-model="edit.location_ids"
                        :options="locations"
                        option-label="name"
                        option-value="id"
                        :disabled="edit.role === 'manager'"
                /></label>
                <p v-for="(message, key) in edit.errors" :key="key" role="alert" class="text-red-600">{{ message }}</p>
                <Button :label="$t('save_access')" type="submit" :loading="edit.processing" /></form></Dialog
        ><ZFConfirmDialog
    /></RestaurantLayout>
</template>
