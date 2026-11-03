<?php

namespace App\Models;

use App\Domain\Mail\RecordPlatformActivity;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public function preferredLocale(): string
    {
        return in_array($this->locale, ['ro', 'en'], true) ? $this->locale : 'ro';
    }

    public function markEmailAsVerified()
    {
        return DB::transaction(function () {
            $saved = parent::markEmailAsVerified();
            if ($saved) {
                app(RecordPlatformActivity::class)->verified(new Verified($this));
            }

            return $saved;
        });
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_country',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'phone',
        'phone_country',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'phone' => 'encrypted',
        ];
    }
}
