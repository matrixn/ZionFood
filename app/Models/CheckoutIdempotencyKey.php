<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutIdempotencyKey extends Model
{
    use HasFactory;

    protected $table = 'checkout_idempotency_keys';

    protected $guarded = ['id'];
}
