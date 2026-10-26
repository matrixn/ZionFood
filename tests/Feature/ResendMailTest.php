<?php

use App\Domain\Mail\EmailQuota;
use App\Jobs\DeliverEmail;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

it('resolves the native Resend transport with the configured API key', function () {
    config(['services.resend.key' => 're_test_not_a_real_key']);

    expect(Mail::mailer('resend')->getSymfonyTransport())->toBeInstanceOf(ResendTransport::class);
});

it('refuses mail tests with invalid recipients, non-delivery mailers or missing Resend credentials', function () {
    $this->artisan('zionfood:mail-test invalid')->assertFailed();
    $this->artisan('zionfood:mail-test recipient@example.test')->assertFailed();
    config(['mail.default' => 'resend', 'services.resend.key' => null]);
    $this->artisan('zionfood:mail-test recipient@example.test')->assertFailed();
});

it('submits the configured sender and recipient through the Resend SDK without network requests', function () {
    Queue::fake();
    config(['email_delivery.cache_store' => 'array']);
    $history = [];
    $handler = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"id":"email-test-id"}')]));
    $handler->push(Middleware::history($history));
    $client = new Resend\Client(new HttpTransporter(
        new Client(['handler' => $handler]),
        BaseUri::from('api.resend.com'),
        Headers::withAuthorization(ApiKey::from('re_test_not_a_real_key')),
    ));
    Mail::extend('resend-test', fn () => new ResendTransport($client));
    config(['mail.default' => 'resend-test', 'mail.mailers.resend-test' => ['transport' => 'resend-test'], 'mail.from' => ['address' => 'noreply@example.test', 'name' => 'ZionFood']]);

    $this->artisan('zionfood:mail-test recipient@example.test')->assertSuccessful();

    expect($history)->toHaveCount(0);
    Queue::assertPushed(DeliverEmail::class, function ($job) {
        $job->handle(app(EmailQuota::class));

        return true;
    });

    expect($history)->toHaveCount(1);
    $request = $history[0]['request'];
    $payload = json_decode((string) $request->getBody(), true);
    expect((string) $request->getUri())->toBe('https://api.resend.com/emails')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer re_test_not_a_real_key')
        ->and($payload['from'])->toBe('"ZionFood" <noreply@example.test>')
        ->and($payload['to'])->toBe(['recipient@example.test'])
        ->and($payload['text'])->toContain('ZionFood');
});

it('does not expose provider errors or secrets when submission fails', function () {
    config(['mail.default' => 'resend', 'services.resend.key' => 're_test_not_a_real_key']);
    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('secret-provider-details'));

    $this->artisan('zionfood:mail-test recipient@example.test')
        ->expectsOutput('Email could not be queued. Check mailer configuration and queue health.')
        ->assertFailed();
});
