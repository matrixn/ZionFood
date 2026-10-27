<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationMembership extends Model
{
    use HasFactory;

    protected $table = 'organization_memberships';

    protected $guarded = ['id'];

    protected $casts = ['permissions' => 'array'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
