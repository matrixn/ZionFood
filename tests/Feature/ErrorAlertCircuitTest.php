<?php

use App\Domain\Mail\EmailErrorAlert;
use App\Domain\Mail\EmailQuota;
use App\Domain\Mail\ErrorAlertCircuit;
use App\Jobs\DeliverEmail;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config(['email_delivery.cache_store' => 'array', 'email_delivery.queue_connection' => 'redis', 'mail.default' => 'smtp', 'email_delivery.alerts_enabled' => true, 'email_delivery.admin_email' => 'admin@example.test']);
});

it('latches on the sixth distinct error and stays suspended after the hour and service restart', function () {
    Queue::fake();
    for ($i = 0; $i < 6; $i++) {
        app(EmailErrorAlert::class)->handle(new MessageLogged('error', 'Error '.$i, []));
    }
    Queue::assertPushed(DeliverEmail::class, 5);
    expect((new ErrorAlertCircuit)->status()['suspended_at'])->not->toBeNull();
    $this->travel(2)->hours();
    expect((new ErrorAlertCircuit)->admit())->toBeNull();
    expect((new ErrorAlertCircuit)->status()['admitted'])->toBe(0);
    app(EmailErrorAlert::class)->handle(new MessageLogged('critical', 'Later error', []));
    Queue::assertPushed(DeliverEmail::class, 5);
    Mail::raw('Normal invitation', fn ($message) => $message->to('staff@example.test'));
    Queue::assertPushed(DeliverEmail::class, 6);
});

it('uses a rolling hour instead of resetting at the clock hour and deduplicates first', function () {
    Queue::fake();
    $this->travelTo(now()->startOfHour()->addMinutes(59));
    for ($i = 0; $i < 5; $i++) {
        app(EmailErrorAlert::class)->handle(new MessageLogged('error', 'Error '.$i, []));
    }
    app(EmailErrorAlert::class)->handle(new MessageLogged('error', 'Error 0', []));
    expect(app(ErrorAlertCircuit::class)->status()['suspended_at'])->toBeNull();
    $this->travel(2)->minutes();
    expect(app(ErrorAlertCircuit::class)->admit())->toBeNull();
    expect(app(ErrorAlertCircuit::class)->status()['admitted'])->toBe(5);
});

it('limits delayed delivery attempts as well and never replays previous generations', function () {
    $gate = app(ErrorAlertCircuit::class);
    $generation = $gate->admit();
    for ($i = 0; $i < 5; $i++) {
        $slot = $gate->reserveDelivery($generation);
        expect($slot)->not->toBeNull();
        $gate->accepted($slot);
    }
    expect($gate->reserveDelivery($generation))->toBeNull()
        ->and($gate->status()['accepted'])->toBe(5);
    $state = $gate->status();
    $gate->configure(true, true, $state['version']);
    expect($gate->status()['suspended_at'])->toBeNull()
        ->and($gate->status()['admitted'])->toBe(0)
        ->and($gate->canDeliver($generation))->toBeFalse()
        ->and($gate->canDeliver(null))->toBeFalse();
    $email = (new Email)->from('app@example.test')->to('admin@example.test')->text('old alert');
    $email->getHeaders()->addTextHeader('X-ZionFood-Error-Alert', 'old');
    $email->getHeaders()->addTextHeader('X-ZionFood-Alert-Generation', (string) $generation);
    Mail::shouldReceive('mailer')->never();
    (new DeliverEmail($email, 'resend'))->handle(app(EmailQuota::class));
    expect($gate->status()['delivery_attempts'])->toBe(0);
});

it('only permits platform admins to inspect and resume alerts with optimistic version checks', function () {
    [$owner] = tenantFixture();
    $this->get('/admin/email-alerts')->assertRedirect('/login');
    $this->actingAs($owner)->get('/admin/email-alerts')->assertForbidden();
    $this->putJson('/admin/email-alerts', ['enabled' => true, 'resume' => true, 'version' => 1])->assertForbidden();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $gate = app(ErrorAlertCircuit::class);
    for ($i = 0; $i < 6; $i++) {
        $gate->admit();
    }
    $version = $gate->status()['version'];
    $this->actingAs($admin)->get('/admin/email-alerts')->assertOk();
    $this->putJson('/admin/email-alerts', ['enabled' => true, 'resume' => true, 'version' => $version - 1])->assertConflict();
    $this->putJson('/admin/email-alerts', ['enabled' => true, 'resume' => true, 'version' => $version])->assertOk()->assertJsonPath('settings.suspended_at', null);
    $this->putJson('/admin/email-alerts', ['enabled' => false, 'resume' => false, 'version' => $version + 1])->assertOk();
    expect($gate->admit())->toBeNull();
    config(['email_delivery.alerts_enabled' => false]);
    $gate->configure(true, true, $version + 2);
    expect($gate->admit())->toBeNull()->and($gate->status()['environment_enabled'])->toBeFalse();
});
