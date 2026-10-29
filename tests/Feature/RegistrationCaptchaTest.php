<?php

use App\Domain\Auth\RegistrationCaptcha;
use App\Domain\Auth\RegistrationChallengeImage;
use App\Domain\Mail\AccountMailBudget;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

function captchaRegistration(array $extra = []): array
{
    return ['name' => 'Receipt guest', 'email' => 'receipt@example.test', 'password' => 'SafeLocalPassword!2026', 'password_confirmation' => 'SafeLocalPassword!2026', ...$extra];
}

function accessibleReceipt(TestCase $test): array
{
    $test->get('/register')->assertOk();
    $challenge = $test->postJson('/register/challenge', ['mode' => 'accessible'])->assertOk()->json();
    preg_match_all('/[0-9]+/', $challenge['prompt'], $numbers);

    return [$challenge, (string) array_sum($numbers[0])];
}

it('renders a local raster receipt without exposing an answer or reusable solution in the payload', function () {
    $this->get('/register')->assertOk();
    $data = $this->postJson('/register/challenge', ['mode' => 'visual'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertJsonMissingPath('answer')->assertJsonMissingPath('proof')->json();
    expect($data['image'])->toStartWith('data:image/png;base64,');
    $size = getimagesizefromstring(base64_decode(substr($data['image'], strlen('data:image/png;base64,'))));
    expect([$size[0], $size[1], $size['mime']])->toBe([640, 168, 'image/png']);
    $record = Cache::get('registration-captcha:challenges:'.hash('sha256', $data['id']));
    expect($record['answer'])->toMatch('/^[a-f0-9]{64}$/')->and($data['prompt'])->toBeNull();
});

it('requires a server-verified receipt and ignores forged frontend flags without queueing mail', function () {
    Notification::fake();
    $this->post('/register', captchaRegistration(['captcha_verified' => true, 'invited' => true]))->assertSessionHasErrors('captcha_proof');
    $this->postJson('/register', captchaRegistration(['captcha_proof' => str_repeat('a', 64)]))->assertUnprocessable()->assertJsonValidationErrors('captcha_proof');
    expect(User::count())->toBe(0);
    Notification::assertNothingSent();
    config(['email_delivery.account_daily_limit' => 1]);
    expect(app(AccountMailBudget::class)->admitRequest('127.0.0.1'))->toBe(0);
});

it('verifies the exact visual code while rejecting a numeric JSON answer without consuming the challenge', function () {
    $code = null;
    $this->mock(RegistrationChallengeImage::class)->shouldReceive('render')->once()->andReturnUsing(function ($value) use (&$code) {
        $code = $value;

        return 'data:image/png;base64,local-test-image';
    });
    $this->get('/register');
    $challenge = $this->postJson('/register/challenge', ['mode' => 'visual'])->assertOk()->json();
    expect($code)->toMatch('/^[0-9]{4}$/');
    $this->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => (int) $code])->assertUnprocessable();
    $this->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => $code])->assertOk()->assertJsonStructure(['proof', 'expires_in']);
    $this->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => $code])->assertUnprocessable();
});

it('accepts accessible verification once and retains email verification and normal owner onboarding', function () {
    Notification::fake();
    $proof = registrationCaptchaData($this);
    $this->post('/register', captchaRegistration($proof))->assertRedirect('/app');
    expect(User::sole()->hasVerifiedEmail())->toBeFalse();
    $this->get('/onboarding')->assertRedirect('/email/verify');
    auth('web')->logout();
    session()->flush();
    $this->post('/register', captchaRegistration([...$proof, 'email' => 'replay@example.test']))->assertSessionHasErrors('captcha_proof');
    expect(User::count())->toBe(1);
});

it('does not burn a proof on ordinary field validation errors', function () {
    Notification::fake();
    $proof = registrationCaptchaData($this);
    $this->post('/register', captchaRegistration([...$proof, 'password' => 'short']))->assertSessionHasErrors('password');
    $this->post('/register', captchaRegistration($proof))->assertRedirect('/app');
    expect(User::count())->toBe(1);
});

it('consumes a wrong answer and rejects replays even with the correct answer afterwards', function () {
    [$challenge, $answer] = accessibleReceipt($this);
    $data = ['challenge_id' => $challenge['id'], 'captcha_answer' => (string) ((int) $answer + 1)];
    $this->postJson('/register/challenge/verify', $data)->assertUnprocessable();
    $this->postJson('/register/challenge/verify', [...$data, 'captcha_answer' => $answer])->assertUnprocessable();
    expect(User::count())->toBe(0);
});

it('binds challenges and proofs to the issuing session without letting foreign sessions burn them', function () {
    [$challenge, $answer] = accessibleReceipt($this);
    $binding = session('registration_captcha.binding');
    $this->withSession(['registration_captcha.binding' => 'foreign-binding'])->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => $answer])->assertUnprocessable();
    $proof = $this->withSession(['registration_captcha.binding' => $binding])->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => $answer])->assertOk()->json('proof');
    $this->withSession(['registration_captcha.binding' => 'foreign-binding'])->postJson('/register', captchaRegistration(['captcha_proof' => $proof]))->assertUnprocessable()->assertJsonValidationErrors('captcha_proof');
    Notification::fake();
    $this->withSession(['registration_captcha.binding' => $binding])->post('/register', captchaRegistration(['captcha_proof' => $proof]))->assertRedirect('/app');
});

it('expires both unsolved challenges and solved proofs after five minutes', function () {
    [$challenge, $answer] = accessibleReceipt($this);
    $this->travel(301)->seconds();
    $this->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => $answer])->assertUnprocessable();
    $proof = registrationCaptchaData($this);
    $this->travel(301)->seconds();
    $this->post('/register', captchaRegistration($proof))->assertSessionHasErrors('captcha_proof');
    expect(User::count())->toBe(0);
});

it('bounds outstanding challenges and throttles challenge traffic without spending the email budget', function () {
    [$challenge, $answer] = accessibleReceipt($this);
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/register/challenge', ['mode' => 'visual'])->assertOk();
    }
    $this->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => $answer])->assertUnprocessable();
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/register/challenge', ['mode' => 'visual'])->assertOk();
    }
    $this->postJson('/register/challenge', ['mode' => 'visual'])->assertTooManyRequests();
    expect(app(AccountMailBudget::class)->admitRequest('127.0.0.1'))->toBe(0);
});

it('localizes the accessible question and prevents caching of verification responses', function () {
    $this->get('/register');
    $challenge = $this->withCredentials()->withCookie('zf_locale', 'en')->postJson('/register/challenge', ['mode' => 'accessible'])->assertOk()->json();
    expect($challenge['prompt'])->toStartWith('For this receipt, add');
    preg_match_all('/[0-9]+/', $challenge['prompt'], $numbers);
    $this->postJson('/register/challenge/verify', ['challenge_id' => $challenge['id'], 'captcha_answer' => (string) array_sum($numbers[0])])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

it('fails closed when the challenge cache is unavailable', function () {
    $this->get('/register');
    Cache::shouldReceive('store')->andThrow(new RuntimeException('cache unavailable'));
    expect(fn () => app(RegistrationCaptcha::class)->issue(request(), 'visual'))->toThrow(RuntimeException::class);
    expect(User::count())->toBe(0);
});
