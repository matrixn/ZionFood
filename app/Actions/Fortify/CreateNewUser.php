<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        Validator::make($input, ['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'password' => ['required', 'confirmed', Password::min(12)]])->validate();

        return User::create(['name' => $input['name'], 'email' => strtolower($input['email']), 'password' => $input['password']]);
    }
}
