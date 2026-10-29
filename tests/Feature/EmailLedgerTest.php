<?php

use App\Domain\Mail\EmailLedger;
use App\Domain\Mail\EmailQuota;
use App\Domain\Mail\IndexQueuedEmails;
use App\Domain\Mail\ResendQuota;
use App\Jobs\DeliverEmail;
use App\Models\EmailDelivery;
use App\Models\User;
use App\Notifications\ReservationNotification;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Resend\Exceptions\ErrorException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    config(['email_delivery.cache_store' => 'array', 'email_delivery.queue_connection' => 'database', 'queue.default' => 'database']);
});

function ledgerEmail(): Email
{
    return (new Email)->from('app@example.test')->to('private@example.test')->cc('copy@example.test')->subject('Private subject')->text('private-reset-token');
}

function usageFixture(int $used = 100): array
{
    return ['emails' => ['daily' => ['used' => $used, 'limit' => 100, 'sent' => 90, 'received' => 10, 'resets_at' => now('UTC')->addDay()->startOfDay()->toIso8601String()], 'monthly' => ['used' => 500, 'limit' => 3000, 'resets_at' => now('UTC')->addDays(15)->toIso8601String()]]];
}

it('resets the provider guard at UTC midnight independently of the rolling application quota', function () {
    $this->travelTo(Carbon::parse('2026-10-27 23:59:00', 'UTC'));
    $quota = app(ResendQuota::class);
    expect($quota->reserve(100)['seconds'])->toBe(0)
        ->and($quota->reserve(1))->toBe(['reason' => 'local_daily_limit', 'seconds' => 60]);
    $this->travel(61)->seconds();
    expect($quota->reserve(1)['seconds'])->toBe(0)->and($quota->status()['reserved'])->toBe(1);
});

it('globally defers provider quota errors without a second send attempt or throwing a job exception', function () {
    $error = new RuntimeException('private wrapper', 0, new ErrorException(['statusCode' => 429, 'name' => 'daily_quota_exceeded', 'message' => 'private-provider-text']));
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andThrow($error);
    Mail::shouldReceive('mailer->getSymfonyTransport')->once()->andReturn($transport);
    foreach (range(1, 2) as $i) {
        $job = new DeliverEmail(ledgerEmail(), 'resend');
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(Mockery::on(fn ($delay) => $delay > 0 && $delay <= 86430));
        $job->setJob($queueJob);
        $job->handle(app(EmailQuota::class));
    }
    expect(EmailDelivery::where('state', 'deferred')->count())->toBe(2)
        ->and((int) EmailDelivery::sum('attempts'))->toBe(1)
        ->and(app(ResendQuota::class)->status()['reason'])->toBe('daily_quota_exceeded');
});

it('keeps monthly and rate pauses distinct and cannot bypass a provider quota by editing the guard', function () {
    $quota = app(ResendQuota::class);
    expect($quota->pause('rate_limit_exceeded')['seconds'])->toBe(60);
    $quota->pause('daily_quota_exceeded');
    $quota->pause('rate_limit_exceeded');
    $quota->configure(200, 1);
    expect($quota->status()['reason'])->toBe('daily_quota_exceeded');
    $quota->pause('monthly_quota_exceeded');
    expect($quota->status()['paused_until'])->toBeNull()->and($quota->delay()['seconds'])->toBe(86400);
    $this->travel(2)->days();
    expect($quota->delay()['reason'])->toBe('monthly_quota_exceeded');
    expect($quota->rejection(new RuntimeException('429 daily_quota_exceeded')))->toBeNull();
});

it('reads account usage including received emails and preserves a pause on an unauthorized usage request', function () {
    config(['services.resend.key' => 'private-api-key']);
    Http::fake(['api.resend.com/usage' => Http::sequence()->push(usageFixture())->push(['message' => 'secret error'], 403)]);
    $quota = app(ResendQuota::class);
    $quota->syncUsage();
    expect($quota->status()['reason'])->toBe('daily_quota_exceeded')->and($quota->status()['reserved'])->toBe(100);
    Http::assertSent(fn ($r) => $r->url() === 'https://api.resend.com/usage' && $r->hasHeader('Authorization', 'Bearer private-api-key'));
    $quota->syncUsage();
    expect($quota->status()['usage_error'])->toBe('usage_not_authorized')->and($quota->status()['reason'])->toBe('daily_quota_exceeded')
        ->and(json_encode($quota->status()))->not->toContain('private-api-key')->not->toContain('secret error');
});

it('uses the provider monthly reset and validates bounded usage responses without clearing existing pauses', function () {
    config(['services.resend.key' => 'fake']);
    $payload = usageFixture(10);
    $payload['emails']['monthly']['used'] = 3000;
    Http::fake(['api.resend.com/usage' => Http::sequence()->push($payload)->push(str_repeat('x', 65537))]);
    $quota = app(ResendQuota::class);
    $quota->syncUsage();
    expect($quota->status()['reason'])->toBe('monthly_quota_exceeded')->and($quota->delay()['seconds'])->toBe(15 * 86400);
    $quota->syncUsage();
    expect($quota->status()['usage_error'])->toBe('usage_invalid')->and($quota->status()['reason'])->toBe('monthly_quota_exceeded');
});

it('encrypts recipient subject and context while excluding bodies and private tokens from metadata', function () {
    $record = app(EmailLedger::class)->message((string) Str::ulid(), ledgerEmail(), 'resend', ['__laravel_notification' => ResetPassword::class]);
    $raw = json_encode((array) DB::table('email_deliveries')->first());
    expect($record->source)->toBe('password_reset')->and($record->recipients['cc'])->toBe(['copy@example.test'])
        ->and($record->expires_at)->not->toBeNull()->and($raw)->not->toContain('private@example.test')->not->toContain('Private subject')->not->toContain('private-reset-token');
    $this->travel(61)->minutes();
    expect(app(EmailLedger::class)->claim($record))->toBe('skip')->and($record->fresh()->state)->toBe('expired');
});

it('does not resubmit a message that was already accepted and records the provider id', function () {
    $email = ledgerEmail();
    $email->getHeaders()->addTextHeader('X-Resend-Email-ID', 'provider-accepted-id');
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andReturn(new SentMessage($email, Envelope::create($email)));
    Mail::shouldReceive('mailer->getSymfonyTransport')->once()->andReturn($transport);
    $job = new DeliverEmail($email, 'resend');
    $job->handle(app(EmailQuota::class));
    $job->handle(app(EmailQuota::class));
    expect(EmailDelivery::sole()->state)->toBe('accepted')->and(EmailDelivery::sole()->provider_email_id)->toBe('provider-accepted-id');
});

it('indexes encrypted existing jobs idempotently without executing or changing their queue payloads', function () {
    $job = new DeliverEmail(ledgerEmail(), 'resend');
    Queue::connection('database')->push($job);
    $payload = DB::table('jobs')->value('payload');
    $index = app(IndexQueuedEmails::class);
    expect($index->execute()['indexed'])->toBe(1)->and($index->execute()['indexed'])->toBe(1)
        ->and(EmailDelivery::count())->toBe(1)->and(EmailDelivery::sole()->source)->toBe('legacy')
        ->and(EmailDelivery::sole()->initiator_kind)->toBe('unknown')
        ->and(DB::table('jobs')->value('payload'))->toBe($payload);
    expect($index->payload(json_encode(['data' => ['commandName' => DeliverEmail::class, 'command' => serialize($job)]])))->toBeFalse();
    Mail::shouldReceive('mailer')->never();
});

it('indexes ready delayed and reserved Redis payloads without popping or migrating jobs', function () {
    config(['email_delivery.queue_connection' => 'redis', 'queue.default' => 'redis']);
    $job = new DeliverEmail(ledgerEmail(), 'resend');
    $payload = json_encode(['data' => ['commandName' => DeliverEmail::class, 'command' => app('encrypter')->encrypt(serialize($job))]]);
    $redis = Mockery::mock();
    $redis->shouldReceive('lrange')->once()->with('queues:default', 0, 199)->andReturn([$payload]);
    $redis->shouldReceive('zrange')->once()->with('queues:default:delayed', 0, 199)->andReturn([$payload]);
    $redis->shouldReceive('zrange')->once()->with('queues:default:reserved', 0, 199)->andReturn([$payload]);
    $queue = Mockery::mock();
    $queue->shouldReceive('getConnection')->once()->andReturn($redis);
    $queue->shouldReceive('getQueue')->with('default')->once()->andReturn('queues:default');
    Queue::shouldReceive('connection')->with('redis')->once()->andReturn($queue);
    expect(app(IndexQueuedEmails::class)->execute())->toBe(['scanned' => 3, 'indexed' => 3, 'truncated' => false])
        ->and(EmailDelivery::count())->toBe(1);
});

it('clears a provider pause only after authoritative usage shows headroom', function () {
    config(['services.resend.key' => 'fake']);
    $quota = app(ResendQuota::class);
    $quota->pause('monthly_quota_exceeded');
    Http::fake(['api.resend.com/usage' => Http::response(usageFixture(10))]);
    $quota->syncUsage();
    expect($quota->delay()['seconds'])->toBe(0)->and($quota->status()['reserved'])->toBe(10);
});

it('captures notification intent before rendering and marks skipped notifications as superseded', function () {
    $user = User::factory()->create();
    $notification = new ReservationNotification(999999, 1);
    $user->notify($notification->onConnection('database'));
    $record = EmailDelivery::sole();
    expect($record->source)->toBe('reservation')->and($record->state)->toBe('notification_queued')->and($record->subject)->toBeNull();
    $payload = json_decode(DB::table('jobs')->value('payload'), true);
    $restored = unserialize(app('encrypter')->decrypt($payload['data']['command']));
    expect($restored->notification->shouldSend($user, 'mail'))->toBeFalse()->and($record->fresh()->state)->toBe('superseded');
});

it('shows platform admins filtered paginated metadata only and rejects tenant users on every endpoint', function () {
    [$owner] = tenantFixture();
    $this->get('/admin/emails')->assertRedirect('/login');
    $this->actingAs($owner)->get('/admin/emails')->assertForbidden();
    $this->getJson('/admin/emails/data')->assertForbidden();
    $this->putJson('/admin/emails/provider', [])->assertForbidden();
    $this->postJson('/admin/emails/provider/usage')->assertForbidden();
    $this->postJson('/admin/emails/index-queue')->assertForbidden();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    foreach (range(1, 26) as $i) {
        app(EmailLedger::class)->message((string) Str::ulid(), ledgerEmail(), 'resend');
    }
    $this->actingAs($admin)->get('/admin/emails')->assertOk();
    $this->getJson('/admin/emails/data?recipient=COPY%40example.test&source=other')->assertOk()->assertJsonPath('messages.total', 26)->assertJsonCount(25, 'messages.data')->assertJsonMissingPath('messages.data.0.context')->assertDontSee('private-reset-token');
    $this->getJson('/admin/emails/data?recipient=copy%40example.test&page=2')->assertJsonCount(1, 'messages.data');
    $this->getJson('/admin/emails/data?state=failed')->assertJsonPath('messages.total', 0);
    $this->getJson('/admin/emails/data?source=invalid')->assertUnprocessable();
    $this->putJson('/admin/emails/provider', ['daily_limit' => 90, 'revision' => 1])->assertOk()->assertJsonPath('provider.daily_limit', 90);
    $this->putJson('/admin/emails/provider', ['daily_limit' => 100, 'revision' => 1])->assertConflict();
});

it('classifies account creation and password reset messages and keeps local development delivery distinct', function () {
    config(['mail.default' => 'array']);
    $this->post('/register', [...registrationCaptchaData($this), 'name' => 'New owner', 'email' => 'new-owner@example.test', 'password' => 'Password123!long', 'password_confirmation' => 'Password123!long'])->assertSessionHasNoErrors();
    expect(EmailDelivery::where('source', 'account_registration')->count())->toBe(1)->and(EmailDelivery::sole()->state)->toBe('local');
    $this->post('/logout');
    $this->post('/forgot-password', ['email' => 'new-owner@example.test'])->assertSessionHasNoErrors();
    expect(EmailDelivery::where('source', 'password_reset')->count())->toBe(1);
});

it('prunes old terminal metadata without deleting deferred emails or their queued payloads', function () {
    $record = app(EmailLedger::class)->message((string) Str::ulid(), ledgerEmail(), 'resend');
    $record->update(['state' => 'accepted', 'updated_at' => now()->subDays(91)]);
    $pending = app(EmailLedger::class)->message((string) Str::ulid(), ledgerEmail(), 'resend');
    $pending->update(['state' => 'deferred', 'updated_at' => now()->subDays(91)]);
    $this->artisan('zionfood:mail-prune')->assertSuccessful();
    expect(EmailDelivery::count())->toBe(1)->and(EmailDelivery::sole()->id)->toBe($pending->id);
});
