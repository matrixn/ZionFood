<?php

use App\Domain\Auth\RegistrationPhone;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function phoneRegistration(array $extra = []): array
{
    return ['name' => 'Phone test', 'email' => 'phone-registration@example.test', 'password' => 'StrongPhonePassword!2026', 'password_confirmation' => 'StrongPhonePassword!2026', ...registrationPhoneData(), ...$extra];
}

it('validates national and international formats and normalizes numbers without losing significant zeros', function ($country, $input, $expected) {
    expect(app(RegistrationPhone::class)->normalize($country, $input))->toBe($expected);
})->with([
    ['RO', '0770 123 456', '+40770123456'], ['RO', '770123456', '+40770123456'], ['RO', '+40 770 123 456', '+40770123456'],
    ['GB', '020 7946 0018', '+442079460018'], ['DE', '030 901820', '+4930901820'],
    ['US', '(202) 555-0123', '+12025550123'], ['CA', '416 555 0123', '+14165550123'],
    ['FR', '06 12 34 56 78', '+33612345678'], ['IT', '02 3661 8300', '+390236618300'], ['AU', '0412 345 678', '+61412345678'],
]);

it('rejects wrong regions shared-prefix mismatches malformed input and extensions', function ($country, $number) {
    expect(app(RegistrationPhone::class)->normalize($country, $number))->toBeNull();
})->with([
    ['RO', '123'], ['RO', '+44 20 7946 0018'], ['US', '+1 416 555 0123'], ['CA', '+1 202 555 0123'],
    ['ZZ', '0770123456'], ['RO', '+40770123456 ext 12'], ['RO', 'Call +40770123456'],
    ['RO', "0770123456\nHeader:value"], ['RO', str_repeat('1', 41)], ['RO', ['0770123456']], [['RO'], '0770123456'],
]);

it('requires both phone fields on the server even when client validation is bypassed', function ($extra, $field) {
    Notification::fake();
    $this->postJson('/register', phoneRegistration($extra))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(User::count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    [['phone' => null], 'phone'], [['phone_country' => null], 'phone_country'], [['phone_country' => 'ZZ'], 'phone_country'],
    [['phone_country' => ['RO']], 'phone_country'], [['phone' => '123'], 'phone'], [['phone' => '+442079460018'], 'phone'],
]);

it('stores encrypted international account contact details without publishing them in user serialization', function () {
    Notification::fake();
    $this->post('/register', [...phoneRegistration(['phone_country' => 'ro', 'phone' => '0770 123 456']), ...registrationCaptchaData($this)])->assertRedirect('/app');
    $user = User::sole();
    expect($user->phone)->toBe('+40770123456')->and($user->phone_country)->toBe('RO')->and($user->hasVerifiedEmail())->toBeFalse();
    expect(DB::table('users')->value('phone'))->not->toContain('0770', '+40770123456');
    expect($user->toArray())->not->toHaveKey('phone')->not->toHaveKey('phone_country');
});

it('does not consume the captcha proof when phone validation fails', function () {
    Notification::fake();
    $proof = registrationCaptchaData($this);
    $this->post('/register', [...phoneRegistration(['phone' => '123']), ...$proof])->assertSessionHasErrors('phone');
    $this->post('/register', [...phoneRegistration(), ...$proof])->assertRedirect('/app');
    expect(User::count())->toBe(1);
});

it('keeps preexisting accounts usable without inventing or backfilling telephone numbers', function () {
    $existing = User::factory()->create();
    expect($existing->fresh()->phone)->toBeNull()->and($existing->fresh()->phone_country)->toBeNull();
    $this->actingAs($existing)->get('/app')->assertRedirect('/onboarding');
});
