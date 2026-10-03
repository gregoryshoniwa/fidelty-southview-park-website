<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['categories' => 'array', 'confirmed_at' => 'datetime'];
    }
}
