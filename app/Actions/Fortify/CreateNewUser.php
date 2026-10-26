<?php

namespace App\Actions\Fortify;

use App\Domain\Tenancy\Actions\AcceptStaffInvitation;
use App\Domain\Tenancy\PendingStaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        $input['email'] = strtolower(trim($input['email'] ?? ''));
        $pending = app(PendingStaffInvitation::class)->current(request());
        Validator::make($input, ['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'password' => ['required', 'confirmed', Password::min(12)]])->validate();

        if ($pending && $input['email'] !== strtolower($pending['record']->email)) {
            throw ValidationException::withMessages(['email' => __('Use the email address from your restaurant invitation.')]);
        }

        $user = DB::transaction(function () use ($input, $pending) {
            $user = User::create(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
            $user->forceFill(['locale' => app()->getLocale()])->save();
            if ($pending) {
                app(AcceptStaffInvitation::class)->execute($user, $pending['record']->public_id, $pending['token']);
            }

            return $user;
        });
        if ($pending) {
            app(PendingStaffInvitation::class)->clear(request());
        }

        return $user;
    }
}
