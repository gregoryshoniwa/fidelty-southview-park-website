<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stand extends Model
{
    protected $guarded = ['id'];

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }
}
