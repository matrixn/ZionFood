<?php

use App\Domain\Mail\EmailDeliveryLog;
use App\Domain\Mail\EmailQuota;
use App\Jobs\DeliverEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Events\MasterSupervisorDeployed;
use Laravel\Horizon\ProvisioningPlan;
use Resend\Exceptions\ErrorException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

it('provisions workers for custom Forge environments as well as production', function () {
    $plan = ProvisioningPlan::get('test-master');
    expect($plan->parsed)->toHaveKey('*')->toHaveKey('production');
    expect($plan->parsed['*']['supervisor-1']->maxProcesses)->toBe(3)
        ->and($plan->parsed['*']['supervisor-1']->queue)->toBe('default');
});

it('keeps the production plan ahead of the custom-environment fallback', function () {
    Event::fake([MasterSupervisorDeployed::class]);
    $commands = Mockery::mock(HorizonCommandQueue::class);
    $commands->shouldReceive('push')->once()->with(Mockery::any(), Mockery::any(), Mockery::on(fn ($options) => $options['maxProcesses'] === 10));
    $commands->shouldReceive('push')->once()->with(Mockery::any(), Mockery::any(), Mockery::on(fn ($options) => $options['maxProcesses'] === 3));
    app()->instance(HorizonCommandQueue::class, $commands);
    $plan = ProvisioningPlan::get('test-master');
    $plan->deploy('production');
    $plan->deploy('custom-staging');
});

it('logs actual provider acceptance with a correlation id and Resend id', function () {
    config(['email_delivery.cache_store' => 'array']);
    $email = (new Email)->from('app@example.test')->to('private@example.test')->text('private-token');
    $accepted = clone $email;
    $accepted->getHeaders()->addTextHeader('X-Resend-Email-ID', 'test-provider-id');
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andReturn(new SentMessage($accepted, Envelope::create($accepted)));
    Mail::shouldReceive('mailer->getSymfonyTransport')->once()->andReturn($transport);
    $logger = Mockery::mock();
    $logger->shouldReceive('log')->with('info', 'ZionFood email processing', Mockery::type('array'))->once();
    $logger->shouldReceive('log')->with('info', 'ZionFood email provider_accepted', Mockery::on(fn ($context) => $context['provider_email_id'] === 'test-provider-id' && filled($context['delivery_id']) && ! str_contains(json_encode($context), 'private')))->once();
    Log::shouldReceive('channel')->with('mail_delivery')->andReturn($logger);
    (new DeliverEmail($email, 'resend'))->handle(app(EmailQuota::class));
});

it('classifies provider errors without logging sensitive error text', function () {
    $error = new RuntimeException('secret wrapped error', 0, new ErrorException(['statusCode' => 403, 'name' => 'validation_error', 'message' => 'Bearer secret-key customer@example.test']));
    $details = EmailDeliveryLog::failure($error);
    expect($details['http_status'])->toBe(403)
        ->and($details['hint'])->toContain('sender domain')
        ->and(json_encode($details))->not->toContain('secret')->not->toContain('customer@example.test');
});

it('does not retry an accepted email when a log sink fails', function () {
    config(['email_delivery.cache_store' => 'array']);
    $email = (new Email)->from('app@example.test')->to('private@example.test')->text('private-token');
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andReturn(new SentMessage($email, Envelope::create($email)));
    Mail::shouldReceive('mailer->getSymfonyTransport')->once()->andReturn($transport);
    Log::shouldReceive('channel')->andThrow(new RuntimeException('log sink failed'));
    (new DeliverEmail($email, 'resend'))->handle(app(EmailQuota::class));
    expect(true)->toBeTrue();
});

it('logs permanent failures without triggering recursive email alerts', function () {
    Queue::fake();
    config(['email_delivery.alerts_enabled' => true, 'email_delivery.admin_email' => 'admin@example.test']);
    EmailDeliveryLog::write('permanently_failed', ['delivery_id' => 'safe-id'], 'error');
    Queue::assertNothingPushed();
});

it('detects a Horizon master without any running supervisors', function () {
    config(['mail.default' => 'resend', 'services.resend.key' => 'fake-key', 'email_delivery.queue_connection' => 'redis', 'email_delivery.cache_store' => 'array']);
    Queue::shouldReceive('connection->size')->andReturn(3);
    Queue::shouldReceive('connection->delayedSize')->andReturn(0);
    Queue::shouldReceive('connection->reservedSize')->andReturn(0);
    $repository = Mockery::mock(SupervisorRepository::class);
    $repository->shouldReceive('all')->andReturn([]);
    app()->instance(SupervisorRepository::class, $repository);
    $this->artisan('zionfood:mail-status')->expectsOutput('Running Horizon supervisors: 0')->assertFailed();
});
