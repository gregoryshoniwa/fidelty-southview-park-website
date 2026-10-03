<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Minute extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'published_at' => 'datetime'];
    }
}
