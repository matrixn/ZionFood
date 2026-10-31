<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ServiceLead extends Model
{
    public const STATUSES = ['new', 'contacted', 'qualified', 'closed'];

    protected $guarded = ['id'];

    protected $hidden = ['email_hash', 'name', 'email', 'phone'];

    protected $casts = ['name' => 'encrypted', 'email' => 'encrypted', 'phone' => 'encrypted'];

    protected static function booted(): void
    {
        static::creating(fn ($lead) => $lead->public_id ??= (string) Str::ulid());
    }

    public function campaignVisit()
    {
        return $this->belongsTo(CampaignVisit::class);
    }

    public static function emailHash(string $email): string
    {
        return hash_hmac('sha256', 'service-lead:'.mb_strtolower(trim($email)), config('app.key'));
    }
}
