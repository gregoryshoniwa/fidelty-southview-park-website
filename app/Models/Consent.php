<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['given_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }
}
