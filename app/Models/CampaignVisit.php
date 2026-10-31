<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CampaignVisit extends Model
{
    public const GROUP_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_id'];

    public const FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    protected $guarded = ['id'];

    protected $hidden = ['campaign_key'];

    protected static function booted(): void
    {
        static::creating(fn ($visit) => $visit->public_id ??= (string) Str::ulid());
    }

    public function leads()
    {
        return $this->hasMany(ServiceLead::class, 'campaign_visit_id');
    }
}
