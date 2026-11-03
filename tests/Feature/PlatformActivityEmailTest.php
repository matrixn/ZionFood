<?php

use App\Domain\Mail\EmailQuota;
use App\Domain\Mail\RecordPlatformActivity;
use App\Domain\Marketing\Actions\CaptureServiceLead;
use App\Domain\Tenancy\Actions\InviteStaff;
use App\Jobs\DeliverEmail;
use App\Jobs\QueuePlatformActivityEmail;
use App\Models\EmailDelivery;
use App\Models\PlatformActivityEmail;
use App\Models\ServiceLead;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config(['email_delivery.queue_connection' => 'database', 'email_delivery.cache_store' => 'array']);
    Queue::fake([QueuePlatformActivityEmail::class]);
});

function activityContact(array $extra = []): array
{
    return ['name' => 'Contact Name', 'email' => 'activity-contact@example.test', 'phone' => '0770123456', 'phone_country' => 'RO', 'locale' => 'ro', ...$extra];
}

function activityMessage(): Email
{
    return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
}

function activityInvitation(): array
{
    Notification::fake();
    [$owner, $location] = tenantFixture('activity-invited');
    $email = 'activity-invited@example.test';
    $invite = app(InviteStaff::class)->execute($owner, $location->organization_id, ['email' => $email, 'role' => 'staff', 'location_ids' => [$location->id]]);
    $token = null;
    Notification::assertSentOnDemand(StaffInvitationNotification::class, function ($notice) use (&$token) {
        $token = $notice->token;

        return true;
    });

    return [$invite, $token, $email];
}

it('records one encrypted contact notice per verified platform administrator and ignores duplicates and honeypots', function () {
    $admin = User::factory()->create(['is_platform_admin' => true, 'locale' => 'ro']);
    $english = User::factory()->create(['is_platform_admin' => true, 'locale' => 'en']);
    User::factory()->unverified()->create(['is_platform_admin' => true]);
    User::factory()->create();
    config(['email_delivery.admin_email' => 'technical-only@example.test']);
    $this->postJson('/interest', activityContact())->assertOk();
    $this->postJson('/interest', activityContact(['name' => 'Duplicate']))->assertOk();
    $this->postJson('/interest', activityContact(['email' => 'honeypot@example.test', 'website' => 'spam']))->assertOk();
    expect(PlatformActivityEmail::count())->toBe(2)->and(EmailDelivery::count())->toBe(2)
        ->and(PlatformActivityEmail::pluck('recipient_id')->all())->toEqual([$admin->id, $english->id]);
    $raw = DB::table('platform_activity_emails')->value('details');
    expect($raw)->not->toContain('Contact Name', 'activity-contact@example.test', '40770123456');
    expect(PlatformActivityEmail::first()->toArray())->not->toHaveKey('details');
    foreach (PlatformActivityEmail::all() as $notice) {
        (new QueuePlatformActivityEmail($notice->id))->handle();
        (new QueuePlatformActivityEmail($notice->id))->handle();
    }
    $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(2)->and($messages[0]->getOriginalMessage()->getTo()[0]->getAddress())->toBe($admin->email)
        ->and($messages[1]->getOriginalMessage()->getSubject())->toBe('ZionFood — New contact request')
        ->and($messages[0]->getOriginalMessage()->getHtmlBody())->toContain('Contact Name', '+40770123456', '/admin/leads')
        ->and(EmailDelivery::where('state', 'local')->count())->toBe(2)
        ->and(PlatformActivityEmail::whereNotNull('details')->count())->toBe(0);
});

it('creates separate registration verification and restaurant notices through the real owner routes', function () {
    User::factory()->create(['is_platform_admin' => true]);
    $this->post('/register', [...registrationPhoneData(), ...registrationCaptchaData($this), 'name' => 'New Owner', 'email' => 'new-owner@example.test', 'password' => 'safe-password-123!', 'password_confirmation' => 'safe-password-123!'])->assertRedirect();
    $owner = User::where('email', 'new-owner@example.test')->firstOrFail();
    expect(PlatformActivityEmail::pluck('source')->all())->toBe(['platform_account_registered']);
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $owner->id, 'hash' => sha1($owner->email)]);
    $this->get($url)->assertRedirect();
    $this->get($url)->assertRedirect();
    expect($owner->fresh()->hasVerifiedEmail())->toBeTrue()->and(PlatformActivityEmail::count())->toBe(2);
    $this->post('/onboarding', ['organization_name' => 'Example Company', 'restaurant_name' => 'New Restaurant', 'slug' => 'activity-restaurant', 'location_name' => 'Downtown', 'address' => 'Example Street 10'])->assertRedirect();
    expect(PlatformActivityEmail::pluck('source')->all())->toBe(['platform_account_registered', 'platform_email_verified', 'platform_restaurant_created']);
    $restaurant = PlatformActivityEmail::where('source', 'platform_restaurant_created')->sole();
    (new QueuePlatformActivityEmail($restaurant->id))->handle();
    expect(activityMessage()->getHtmlBody())->toContain('New Restaurant', 'Example Company', 'Example Street 10', '+40770123456', 'new-owner@example.test', '/admin/organizations/');
});

it('does not record notifications for invalid registration or unverified verification events', function () {
    User::factory()->create(['is_platform_admin' => true]);
    $this->postJson('/register', ['email' => 'invalid'])->assertUnprocessable();
    event(new Verified(User::factory()->unverified()->create()));
    expect(PlatformActivityEmail::count())->toBe(0);
});

it('records invitation-proven verification once without requiring an extra verification email', function () {
    [$invite, $token, $email] = activityInvitation();
    User::factory()->create(['is_platform_admin' => true]);
    $this->postJson('/invitations/'.$invite->public_id.'/prepare', ['token' => $token])->assertOk();
    $this->post('/register', [...registrationPhoneData(), 'name' => 'Invited Staff', 'email' => $email, 'password' => 'safe-password-123!', 'password_confirmation' => 'safe-password-123!'])->assertRedirect('/app');
    $user = User::where('email', $email)->firstOrFail();
    event(new Verified($user));
    expect(PlatformActivityEmail::pluck('source')->all())->toBe(['platform_account_registered', 'platform_email_verified'])
        ->and(PlatformActivityEmail::first()->details['fields']['account_kind'])->toBe('invited_staff');
});

it('commits verification and its administrator notice atomically', function () {
    User::factory()->create(['is_platform_admin' => true]);
    $user = User::factory()->unverified()->create();
    $this->mock(RecordPlatformActivity::class)->shouldReceive('verified')->once()->andThrow(new RuntimeException('fixture persistence failure'));
    expect(fn () => $user->markEmailAsVerified())->toThrow(RuntimeException::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()->and(PlatformActivityEmail::count())->toBe(0);
});

it('includes server-attributed campaign data and exposes new source filters only to platform administrators', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->get('/?utm_source=facebook&utm_medium=paid&utm_campaign=Launch')->assertOk();
    $this->postJson('/interest', activityContact(['utm_source' => 'forged']))->assertExactJson(['status' => 'received']);
    expect(PlatformActivityEmail::sole()->details['fields']['utm_source'])->toBe('facebook')
        ->and(PlatformActivityEmail::sole()->details['fields']['utm_campaign'])->toBe('Launch');
    $this->getJson('/admin/emails/data?source=platform_contact_request')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson('/admin/emails/data?source=platform_contact_request')->assertForbidden();
    $this->actingAs($admin)->getJson('/admin/emails/data?source=platform_contact_request')->assertOk()->assertJsonPath('messages.total', 1)->assertJsonPath('messages.data.0.source', 'platform_contact_request');
});

it('rolls back business data and notification intent together', function () {
    User::factory()->create(['is_platform_admin' => true]);
    expect(fn () => DB::transaction(function () {
        app(CaptureServiceLead::class)->execute(activityContact());
        throw new RuntimeException('rollback fixture');
    }))->toThrow(RuntimeException::class);
    expect(ServiceLead::count())->toBe(0)->and(PlatformActivityEmail::count())->toBe(0)->and(EmailDelivery::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('keeps the business operation successful when immediate enqueue fails and recovers pending intent', function () {
    User::factory()->create(['is_platform_admin' => true]);
    app(CaptureServiceLead::class)->execute(activityContact());
    $notice = PlatformActivityEmail::sole();
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('private connection diagnostic'));
    expect(app(RecordPlatformActivity::class)->enqueue($notice->id))->toBeFalse()
        ->and(ServiceLead::count())->toBe(1)->and($notice->fresh()->queued_at)->toBeNull();
    (new QueuePlatformActivityEmail($notice->id))->handle();
    expect(EmailDelivery::sole()->state)->toBe('local');
});

it('suppresses private details when the recipient loses administrator access before rendering', function ($change) {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    app(CaptureServiceLead::class)->execute(activityContact());
    $admin->forceFill($change)->save();
    (new QueuePlatformActivityEmail(PlatformActivityEmail::sole()->id))->handle();
    expect(EmailDelivery::sole()->state)->toBe('suppressed')->and(PlatformActivityEmail::sole()->details)->toBeNull()
        ->and(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
})->with([[['is_platform_admin' => false]], [['email_verified_at' => null]]]);

it('suppresses notices whose source was deleted and expires old pending activity', function ($expired) {
    User::factory()->create(['is_platform_admin' => true]);
    app(CaptureServiceLead::class)->execute(activityContact());
    if ($expired) {
        $this->travel(31)->days();
    } else {
        ServiceLead::sole()->delete();
    }
    (new QueuePlatformActivityEmail(PlatformActivityEmail::sole()->id))->handle();
    expect(EmailDelivery::sole()->state)->toBe($expired ? 'expired' : 'suppressed')
        ->and(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
})->with([true, false]);

it('escapes untrusted contact HTML and excludes secrets from message metadata', function () {
    User::factory()->create(['is_platform_admin' => true]);
    app(CaptureServiceLead::class)->execute(activityContact(['name' => '<script>alert(1)</script>']));
    (new QueuePlatformActivityEmail(PlatformActivityEmail::sole()->id))->handle();
    expect(activityMessage()->getHtmlBody())->not->toContain('<script>')->toContain('&lt;script&gt;')
        ->and(activityMessage()->getTextBody())->toContain('<script>alert(1)</script>');
    $raw = json_encode((array) DB::table('email_deliveries')->sole());
    expect($raw)->not->toContain('activity-contact@example.test', '<script>', 'password', 'captcha_proof');
});

it('uses encrypted persistent delivery jobs and rechecks the administrator before transport', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    config(['mail.default' => 'resend', 'services.resend.key' => 're_local_test_only']);
    app(CaptureServiceLead::class)->execute(activityContact());
    (new QueuePlatformActivityEmail(PlatformActivityEmail::sole()->id))->handle();
    $payload = DB::table('jobs')->where('payload', 'like', '%DeliverEmail%')->value('payload');
    expect($payload)->not->toBeNull()->not->toContain('activity-contact@example.test', 'Contact Name');
    $job = unserialize(app('encrypter')->decrypt(json_decode($payload, true)['data']['command']));
    expect($job)->toBeInstanceOf(DeliverEmail::class)->and(EmailDelivery::sole()->source)->toBe('platform_contact_request');
    $admin->forceFill(['is_platform_admin' => false])->save();
    Mail::shouldReceive('mailer')->never();
    $job->handle(app(EmailQuota::class));
    expect(EmailDelivery::sole()->state)->toBe('suppressed');
});

it('recovers a lost transport handoff with the same delivery identity and suppresses already accepted retries', function () {
    User::factory()->create(['is_platform_admin' => true]);
    config(['mail.default' => 'resend', 'services.resend.key' => 're_local_test_only']);
    Queue::fake([QueuePlatformActivityEmail::class, DeliverEmail::class]);
    app(CaptureServiceLead::class)->execute(activityContact());
    $notice = PlatformActivityEmail::sole();
    (new QueuePlatformActivityEmail($notice->id))->handle();
    $id = EmailDelivery::sole()->public_id;
    $this->travel(6)->minutes();
    $this->artisan('zionfood:platform-activity-mail')->assertSuccessful();
    (new QueuePlatformActivityEmail($notice->id))->handle();
    expect(EmailDelivery::count())->toBe(1)->and(EmailDelivery::sole()->public_id)->toBe($id);
    Queue::assertPushed(DeliverEmail::class, 2);
    EmailDelivery::sole()->update(['state' => 'accepted']);
    $this->travel(6)->minutes();
    (new QueuePlatformActivityEmail($notice->id))->handle();
    Queue::assertPushed(DeliverEmail::class, 2);
    expect($notice->fresh()->details)->toBeNull();
});

it('retains deduplication identity while pruning terminal activity snapshots', function () {
    User::factory()->create(['is_platform_admin' => true]);
    app(CaptureServiceLead::class)->execute(activityContact());
    EmailDelivery::sole()->update(['state' => 'accepted']);
    $this->artisan('zionfood:mail-prune')->assertSuccessful();
    expect(PlatformActivityEmail::sole()->details)->toBeNull();
    app(RecordPlatformActivity::class)->contact(ServiceLead::sole());
    expect(PlatformActivityEmail::count())->toBe(1);
});
