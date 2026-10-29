<?php

namespace App\Domain\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RegistrationCaptcha
{
    public function prepare(Request $request): void
    {
        if (! $request->session()->has('registration_captcha.binding')) {
            $request->session()->put('registration_captcha.binding', Str::random(40));
        }
    }

    private function store()
    {
        $store = app()->environment('testing') ? config('cache.default') : config('registration_captcha.cache_store');
        if (! app()->environment('testing') && config('cache.stores.'.$store.'.driver') !== 'redis') {
            throw new RuntimeException('Registration verification requires shared Redis storage.');
        }

        return Cache::store($store);
    }

    private function binding(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->session()->get('registration_captcha.binding'), config('app.key'));
    }

    private function retain(Request $request, string $kind, string $token, array $values): void
    {
        $cache = $this->store();
        $tokens = $request->session()->get('registration_captcha.'.$kind, []);
        $tokens[] = $token;
        while (count($tokens) > config('registration_captcha.max_outstanding')) {
            $cache->forget('registration-captcha:'.$kind.':'.hash('sha256', array_shift($tokens)));
        }
        $request->session()->put('registration_captcha.'.$kind, $tokens);
        if (! $cache->put('registration-captcha:'.$kind.':'.hash('sha256', $token), ['binding' => $this->binding($request), 'expires_at' => now()->timestamp + config('registration_captcha.ttl_seconds'), ...$values], config('registration_captcha.ttl_seconds'))) {
            throw new RuntimeException('Unable to persist registration verification.');
        }
    }

    public function issue(Request $request, string $mode): array
    {
        $this->prepare($request);
        $id = Str::random(40);
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $prompt = null;
        if ($mode === 'accessible') {
            $left = random_int(1, 9);
            $right = random_int(1, 9);
            $code = (string) ($left + $right);
            $prompt = __('For this receipt, add :left and :right.', ['left' => $left, 'right' => $right]);
        }
        $this->retain($request, 'challenges', $id, ['answer' => hash_hmac('sha256', $id.'|'.$code, config('app.key'))]);

        return ['id' => $id, 'mode' => $mode, 'image' => $mode === 'visual' ? app(RegistrationChallengeImage::class)->render($code) : null, 'prompt' => $prompt, 'max_digits' => $mode === 'visual' ? 4 : 2];
    }

    private function consume(Request $request, string $kind, string $token, callable $check): void
    {
        $cache = $this->store();
        $key = 'registration-captcha:'.$kind.':'.hash('sha256', $token);
        $cache->lock($key.':lock', 5)->block(2, function () use ($request, $cache, $key, $check) {
            $record = $cache->get($key);
            if (! $request->session()->has('registration_captcha.binding') || ! is_array($record) || ! hash_equals($record['binding'], $this->binding($request)) || $record['expires_at'] <= now()->timestamp) {
                throw ValidationException::withMessages(['captcha_proof' => __('The access receipt expired or is invalid. Please complete a new one.')]);
            }
            // A foreign session cannot consume another visitor's challenge/proof; a scoped attempt is one-use.
            if (! $cache->forget($key)) {
                throw new RuntimeException('Unable to consume registration verification.');
            }
            $check($record);
        });
    }

    public function verify(Request $request, string $id, string $answer): array
    {
        $this->consume($request, 'challenges', $id, function ($record) use ($id, $answer) {
            if (! hash_equals($record['answer'], hash_hmac('sha256', $id.'|'.$answer, config('app.key')))) {
                throw ValidationException::withMessages(['captcha_proof' => __('That receipt does not match. Please try a new one.')]);
            }
        });
        $proof = Str::random(64);
        $this->retain($request, 'proofs', $proof, []);

        return ['proof' => $proof, 'expires_in' => config('registration_captcha.ttl_seconds')];
    }

    public function consumeProof(Request $request, mixed $proof): void
    {
        if (! is_string($proof) || ! preg_match('/^[a-zA-Z0-9]{64}$/D', $proof)) {
            throw ValidationException::withMessages(['captcha_proof' => __('Complete the ZionFood access receipt before creating your account.')]);
        }
        $this->consume($request, 'proofs', $proof, fn () => null);
    }
}
