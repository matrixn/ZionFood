<?php

use App\Domain\Tenancy\Actions\InviteStaff;
use App\Models\LocationStaff;
use App\Models\OrganizationMembership;
use App\Models\RestaurantLocation;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->travelTo(now()->setTime(12, 0));
});
afterEach(fn () => Str::createRandomStringsNormally());
function inviteFixture(User $owner, RestaurantLocation $location, string $email = 'staff@example.test', string $role = 'staff'): StaffInvitation
{
    Str::createRandomStringsUsing(fn ($length) => str_repeat('x', $length));

    return app(InviteStaff::class)->execute($owner, $location->organization_id, ['email' => $email, 'role' => $role, 'location_ids' => [$location->id]]);
}
it('defaults all browser surfaces to Romanian and validates locale selection', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get('/')->assertOk()->assertHeader('Content-Language', 'ro')->assertSee('lang="ro"', false);
    $this->postJson('/locale', ['locale' => 'fr'])->assertUnprocessable();
    $this->postJson('/locale', ['locale' => 'en'])->assertOk()->assertCookie('zf_locale')->assertHeader('Content-Language', 'en');
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/locale', ['locale' => 'en'])->assertOk();
    expect($user->fresh()->locale)->toBe('en');
    $this->get('/app')->assertHeader('Content-Language', 'en');
});
it('localizes validation and respects explicit integration language headers', function () {
    $this->postJson('/locale', [])->assertUnprocessable()->assertJsonPath('errors.locale.0', 'Câmpul locale este obligatoriu.');
    $this->withHeader('Accept-Language', 'en')->postJson('/api/v1/public/locations/missing/checkout', [])->assertUnprocessable()->assertHeader('Content-Language', 'en');
});
it('invites without granting access before verified acceptance and limits location access', function () {
    [$owner, $location] = tenantFixture();
    $staff = User::factory()->create(['email' => 'staff@example.test']);
    $invitation = inviteFixture($owner, $location);
    expect(OrganizationMembership::where('user_id', $staff->id)->exists())->toBeFalse();
    Notification::assertSentOnDemand(StaffInvitationNotification::class);
    $this->actingAs($staff)->post('/invitations/'.$invitation->public_id, ['token' => str_repeat('x', 64)])->assertRedirect('/app');
    $membership = OrganizationMembership::where('user_id', $staff->id)->firstOrFail();
    expect($membership->role)->toBe('staff');
    expect(LocationStaff::where('user_id', $staff->id)->value('location_id'))->toBe($location->id);
    $this->getJson('/api/v1/locations/'.$location->public_id.'/orders')->assertOk();
    $other = RestaurantLocation::factory()->create(['organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id]);
    $this->getJson('/api/v1/locations/'.$other->public_id.'/orders')->assertForbidden();
    $this->post('/invitations/'.$invitation->public_id, ['token' => str_repeat('x', 64)])->assertRedirect('/app');
    expect(OrganizationMembership::where('user_id', $staff->id)->count())->toBe(1);
});
it('rejects invitation acceptance by a different address and expired or revoked invitations', function () {
    [$owner, $location] = tenantFixture();
    $invitation = inviteFixture($owner, $location);
    $wrong = User::factory()->create();
    $this->actingAs($wrong)->postJson('/invitations/'.$invitation->public_id, ['token' => str_repeat('x', 64)])->assertForbidden();
    $staff = User::factory()->create(['email' => 'staff@example.test']);
    $this->actingAs($staff);
    $this->postJson('/invitations/'.$invitation->public_id, ['token' => str_repeat('z', 64)])->assertForbidden();
    $invitation->update(['expires_at' => now()->subSecond()]);
    $this->postJson('/invitations/'.$invitation->public_id, ['token' => str_repeat('x', 64)])->assertGone();
    $invitation->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
    $this->postJson('/invitations/'.$invitation->public_id, ['token' => str_repeat('x', 64)])->assertGone();
});
it('rejects cross organization invitations assignments and staff management', function () {
    [$owner, $a] = tenantFixture('first');
    [, $b] = tenantFixture('second');
    $this->actingAs($owner)->postJson('/app/locations/'.$a->public_id.'/staff/invitations', ['email' => 'staff@example.test', 'role' => 'staff', 'location_ids' => [$b->id]])->assertUnprocessable();
    $this->postJson('/app/locations/'.$b->public_id.'/staff/invitations', ['email' => 'staff@example.test', 'role' => 'staff', 'location_ids' => [$b->id]])->assertForbidden();
    $this->get('/app/locations/'.$b->public_id.'/staff')->assertForbidden();
});
it('prevents manager escalation and changes to protected owners', function () {
    [$owner, $location] = tenantFixture();
    $manager = User::factory()->create();
    $membership = OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $manager->id, 'role' => 'manager']);
    $ownerMembership = OrganizationMembership::where('user_id', $owner->id)->firstOrFail();
    $this->actingAs($manager)->postJson('/app/locations/'.$location->public_id.'/staff/invitations', ['email' => 'new@example.test', 'role' => 'manager', 'location_ids' => []])->assertForbidden();
    $this->putJson('/app/locations/'.$location->public_id.'/staff/'.$membership->id, ['role' => 'manager', 'location_ids' => []])->assertForbidden();
    $this->actingAs($owner)->delete('/app/locations/'.$location->public_id.'/staff/'.$ownerMembership->id)->assertForbidden();
});
it('updates staff assignments and removes only organization membership and scoped tokens', function () {
    [$owner, $location] = tenantFixture('first');
    [, $foreign] = tenantFixture('second');
    $staff = User::factory()->create();
    $membership = OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff']);
    OrganizationMembership::create(['organization_id' => $foreign->organization_id, 'user_id' => $staff->id, 'role' => 'staff']);
    $token = $staff->createToken('local', ['orders:read']);
    $token->accessToken->forceFill(['organization_id' => $location->organization_id])->save();
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/staff/'.$membership->id, ['role' => 'staff', 'location_ids' => [$location->id]])->assertSessionHasNoErrors();
    expect(LocationStaff::where('user_id', $staff->id)->count())->toBe(1);
    $this->delete('/app/locations/'.$location->public_id.'/staff/'.$membership->id)->assertRedirect();
    expect(LocationStaff::where('organization_id', $location->organization_id)->where('user_id', $staff->id)->count())->toBe(0);
    expect($staff->tokens()->count())->toBe(0);
    expect($staff->fresh())->not->toBeNull();
    expect(OrganizationMembership::where('organization_id', $foreign->organization_id)->where('user_id', $staff->id)->exists())->toBeTrue();
});
it('saves public branding and safely changes the managed hostname', function () {
    Storage::fake('public');
    [$owner,$location] = tenantFixture();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/h9sAAAAASUVORK5CYII=');
    $this->actingAs($owner)->post('/app/locations/'.$location->public_id.'/branding', ['name' => 'Bistro Nou', 'slug' => 'bistro-nou', 'tagline' => 'Gust bun', 'description' => 'Descriere', 'primary_color' => '#176943', 'contact_phone' => '0712345678', 'contact_email' => 'contact@example.test', 'website' => 'https://example.com', 'logo' => UploadedFile::fake()->createWithContent('logo.png', $png)])->assertSessionHasNoErrors();
    $restaurant = $location->restaurant->fresh();
    expect($restaurant->name)->toBe('Bistro Nou');
    Storage::disk('public')->assertExists($restaurant->logo_path);
    $this->getJson('/api/v1/public/locations/'.$location->public_id.'/menu')->assertOk()->assertJsonPath('restaurant.primary_color', '#176943')->assertJsonPath('restaurant.tagline', 'Gust bun');
    $this->assertDatabaseHas('storefront_domains', ['hostname' => 'bistro-nou.zionfood.ro', 'location_id' => $location->id]);
});
it('rejects foreign branding reserved addresses unsafe colors and unsafe logos', function () {
    [$owner,$a] = tenantFixture('first');
    [, $b] = tenantFixture('second');
    $data = ['name' => 'Test', 'slug' => 'updated', 'primary_color' => '#635BFF'];
    $this->actingAs($owner)->postJson('/app/locations/'.$b->public_id.'/branding', $data)->assertForbidden();
    $this->postJson('/app/locations/'.$a->public_id.'/branding', [...$data, 'slug' => 'app'])->assertUnprocessable();
    $this->postJson('/app/locations/'.$a->public_id.'/branding', [...$data, 'primary_color' => 'url(javascript:evil)'])->assertUnprocessable();
    $this->post('/app/locations/'.$a->public_id.'/branding', [...$data, 'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg><script>alert(1)</script></svg>')], ['Accept' => 'application/json'])->assertUnprocessable();
});
it('does not send obsolete invitation notifications after revocation or resend', function () {
    [$owner,$location] = tenantFixture();
    $invitation = inviteFixture($owner, $location);
    $notification = new StaffInvitationNotification($invitation->public_id, str_repeat('x', 64), $location->organization_id);
    expect($notification->shouldSend(new stdClass, 'mail'))->toBeTrue();
    expect($notification)->toBeInstanceOf(ShouldBeEncrypted::class);
    $invitation->update(['revoked_at' => now()]);
    expect($notification->shouldSend(new stdClass, 'mail'))->toBeFalse();
});
