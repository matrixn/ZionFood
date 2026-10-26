<?php

use App\Domain\Tenancy\Actions\InviteStaff;
use App\Models\LocationStaff;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function registrationInvite(string $slug): array
{
    Notification::fake();
    [$owner, $location] = tenantFixture($slug);
    $email = $slug.'@example.test';
    $invite = app(InviteStaff::class)->execute($owner, $location->organization_id, ['email' => $email, 'role' => 'staff', 'location_ids' => [$location->id]]);
    $token = null;
    Notification::assertSentOnDemand(StaffInvitationNotification::class, function ($notice) use (&$token) {
        $token = $notice->token;

        return true;
    });

    return [$location, $invite, $token, $email];
}

it('prefills only proven invited email and registers directly into assigned staff access without verification mail', function () {
    [$location, $invite, $token, $email] = registrationInvite('invited-register');
    $this->get('/invitations/'.$invite->public_id)->assertOk()->assertDontSee($email);
    $this->postJson('/invitations/'.$invite->public_id.'/prepare', ['token' => $token])->assertOk()->assertJsonPath('email', $email);
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('invitedEmail', $email));
    $this->post('/register', ['name' => 'New Staff', 'email' => strtoupper($email), 'password' => 'safe-password-123!', 'password_confirmation' => 'safe-password-123!'])->assertRedirect('/app');
    $user = User::where('email', $email)->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeTrue()->and($invite->fresh()->accepted_by)->toBe($user->id)
        ->and(OrganizationMembership::where('user_id', $user->id)->value('role'))->toBe('staff')
        ->and(LocationStaff::where('user_id', $user->id)->value('location_id'))->toBe($location->id);
    Notification::assertNotSentTo($user, VerifyEmail::class);
    $this->get('/app')->assertRedirect('/app/locations/'.$location->public_id);
    $this->get('/app/locations/'.$location->public_id.'/menu')->assertForbidden();
});

it('does not disclose invited email or prefill registration from an id without its correct proof', function () {
    [, $invite, , $email] = registrationInvite('invited-invalid');
    $this->postJson('/invitations/'.$invite->public_id.'/prepare', ['token' => str_repeat('z', 64)])->assertForbidden()->assertDontSee($email);
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('invitedEmail', null));
});

it('rejects substituted emails and revoked pending registrations without creating a user or membership', function () {
    [, $invite, $token, $email] = registrationInvite('invited-revoked');
    $this->postJson('/invitations/'.$invite->public_id.'/prepare', ['token' => $token])->assertOk();
    $this->post('/register', ['name' => 'Staff', 'email' => 'wrong@example.test', 'password' => 'safe-password-123!', 'password_confirmation' => 'safe-password-123!'])->assertSessionHasErrors('email');
    expect(User::where('email', 'wrong@example.test')->exists())->toBeFalse();
    $invite->update(['revoked_at' => now()]);
    $this->post('/register', ['name' => 'Staff', 'email' => $email, 'password' => 'safe-password-123!', 'password_confirmation' => 'safe-password-123!'])->assertGone();
    expect(User::where('email', $email)->exists())->toBeFalse();
});

it('accepts a valid emailed invitation for an existing unverified matching account', function () {
    [$location, $invite, $token, $email] = registrationInvite('invited-existing');
    $user = User::factory()->unverified()->create(['email' => $email]);
    $this->actingAs($user)->post('/invitations/'.$invite->public_id, ['token' => 'wrong'.str_repeat('x', 59)])->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->post('/invitations/'.$invite->public_id, ['token' => $token])->assertRedirect('/app');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()->and(LocationStaff::where('user_id', $user->id)->value('location_id'))->toBe($location->id);
});

it('retains email verification and owner onboarding for ordinary registration', function () {
    Notification::fake();
    $this->post('/register', ['name' => 'Normal Owner', 'email' => 'normal-owner@example.test', 'password' => 'safe-password-123!', 'password_confirmation' => 'safe-password-123!', 'invitation' => 'forged', 'email_verified_at' => now()])->assertRedirect('/app');
    $user = User::where('email', 'normal-owner@example.test')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse()->and(OrganizationMembership::where('user_id', $user->id)->exists())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});
