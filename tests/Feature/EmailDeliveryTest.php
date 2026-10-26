<?php

use App\Domain\Mail\EmailErrorAlert;
use App\Domain\Mail\EmailQuota;
use App\Jobs\DeliverEmail;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config(['email_delivery.cache_store' => 'array', 'email_delivery.queue_connection' => 'redis']);
});

it('allows exactly 140 recipients across a rolling day without a midnight reset', function () {
    $this->travelTo(now()->startOfDay()->addHours(23));
    $quota = app(EmailQuota::class);
    expect($quota->reserve(100))->toBe(0);
    $this->travel(2)->hours();
    expect($quota->reserve(40))->toBe(0)
        ->and($quota->reserve(1))->toBe(22 * 3600);
    $this->travel(22)->hours();
    expect($quota->reserve(100))->toBe(0)
        ->and($quota->reserve(1))->toBe(2 * 3600);
});

it('rejects oversized messages and fails closed if the shared quota cache is unavailable', function () {
    expect(fn () => app(EmailQuota::class)->reserve(141))->toThrow(RuntimeException::class);
    Cache::shouldReceive('store')->andThrow(new RuntimeException('cache unavailable'));
    expect(fn () => app(EmailQuota::class)->reserve(1))->toThrow(RuntimeException::class);
});

it('queues every delivery mailer while leaving array and log development mail local', function () {
    Queue::fake();
    config(['mail.mailers.secondary' => ['transport' => 'smtp', 'host' => 'localhost', 'port' => 2525]]);
    Mail::mailer('secondary')->raw('private reset token', fn ($message) => $message->to('user@example.test')->subject('Test'));
    Mail::mailer('array')->raw('local only', fn ($message) => $message->to('user@example.test'));
    Queue::assertPushed(DeliverEmail::class, 1);
    Queue::assertPushed(DeliverEmail::class, fn ($job) => $job->afterCommit && $job->mailer === 'secondary');
});

it('defers quota overflow without calling the provider and retains the original message', function () {
    app(EmailQuota::class)->reserve(140);
    $email = (new Email)->from('app@example.test')->to('user@example.test')->subject('Test')->text('original');
    $job = new DeliverEmail($email, 'resend');
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(Mockery::on(fn ($delay) => $delay >= 86400 && $delay <= 86430));
    $job->setJob($queueJob);
    Mail::shouldReceive('mailer')->never();
    $job->handle(app(EmailQuota::class));
    expect($job->email->getTextBody())->toBe('original');
});

it('counts to cc and bcc recipients and retains quota on uncertain provider failures', function () {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andThrow(new RuntimeException('provider timeout'));
    Mail::shouldReceive('mailer->getSymfonyTransport')->once()->andReturn($transport);
    $job = new DeliverEmail((new Email)->from('app@example.test')->to('one@example.test')->cc('two@example.test')->bcc('three@example.test')->text('test'), 'resend');
    expect(fn () => $job->handle(app(EmailQuota::class)))->toThrow(RuntimeException::class);
    expect(Cache::store('array')->get('mail:quota:slots'))->toHaveCount(3);
});

it('encrypts queued MIME content including attachments and private tokens', function () {
    $email = (new Email)->from('app@example.test')->to('one@example.test')->text('private-reset-token')->attach('private attachment', 'test.txt');
    $job = new DeliverEmail($email, 'resend');
    Queue::connection('database')->push($job);
    $payload = json_decode(DB::table('jobs')->value('payload'), true);
    expect($payload['data']['command'])->not->toContain('private-reset-token')->not->toContain('private attachment');
    $restored = unserialize(app('encrypter')->decrypt($payload['data']['command']));
    expect($restored->email->getTextBody())->toBe('private-reset-token')
        ->and($restored->email->getAttachments())->toHaveCount(1);
});

it('emails error logs and reported exceptions once per fingerprint without exposing secrets', function () {
    Queue::fake();
    config(['mail.default' => 'smtp', 'email_delivery.alerts_enabled' => true, 'email_delivery.admin_email' => 'admin@example.test']);
    $exception = new RuntimeException('password=secret-token and customer@example.test');
    report($exception);
    report($exception);
    Log::warning('not an error');
    Queue::assertPushed(DeliverEmail::class, 1);
    Queue::assertPushed(DeliverEmail::class, function ($job) {
        $body = $job->email->getTextBody();
        expect($body)->toContain('RuntimeException')->not->toContain('secret-token')->not->toContain('customer@example.test');
        expect($job->email->getTo()[0]->getAddress())->toBe('admin@example.test');

        return $job->email->getHeaders()->has('X-ZionFood-Error-Alert');
    });
    $this->travel(11)->minutes();
    report($exception);
    Log::error('another application error');
    Queue::assertPushed(DeliverEmail::class, 3);
});

it('does not replace the original exception if alert infrastructure fails', function () {
    config(['email_delivery.alerts_enabled' => true, 'email_delivery.admin_email' => 'admin@example.test']);
    Cache::shouldReceive('store')->andThrow(new RuntimeException('cache failure'));
    app(EmailErrorAlert::class)->handle(new MessageLogged('error', 'original error', []));
    expect(true)->toBeTrue();
});

it('ignores disabled alerts and invalid admin addresses', function () {
    Queue::fake();
    config(['email_delivery.alerts_enabled' => false, 'email_delivery.admin_email' => 'admin@example.test']);
    Log::error('disabled alert');
    config(['email_delivery.alerts_enabled' => true, 'email_delivery.admin_email' => 'invalid']);
    Log::critical('invalid address');
    Queue::assertNothingPushed();
});

it('captures every error severity but excludes lower level debug messages', function () {
    Queue::fake();
    config(['mail.default' => 'smtp', 'email_delivery.alerts_enabled' => true, 'email_delivery.admin_email' => 'admin@example.test']);
    foreach (['error', 'critical', 'alert', 'emergency', 'warning', 'notice', 'info', 'debug'] as $level) {
        Log::log($level, 'Generic test '.$level);
    }
    Queue::assertPushed(DeliverEmail::class, 4);
});

it('rejects synchronous delivery queues instead of silently bypassing the quota', function () {
    config(['email_delivery.queue_connection' => 'sync']);
    expect(fn () => Mail::mailer('smtp')->raw('test', fn ($message) => $message->to('user@example.test')))->toThrow(InvalidArgumentException::class);
});

it('does not create a recursive error alert when alert delivery fails', function () {
    Queue::fake();
    config(['email_delivery.alerts_enabled' => true, 'email_delivery.admin_email' => 'admin@example.test']);
    $email = (new Email)->from('app@example.test')->to('admin@example.test')->text('alert');
    $email->getHeaders()->addTextHeader('X-ZionFood-Error-Alert', 'test-id');
    $job = new DeliverEmail($email, 'resend');
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(3600);
    $job->setJob($queueJob);
    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('provider unavailable'));
    $job->handle(app(EmailQuota::class));
    Queue::assertNothingPushed();
});
