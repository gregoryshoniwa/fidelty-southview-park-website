<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPage extends Model
{
    protected $guarded = ['id'];

    public const TYPES = ['business' => 'Business', 'church' => 'Church', 'school' => 'School'];

    protected function casts(): array
    {
        return ['hours' => 'array', 'verified' => 'boolean', 'active' => 'boolean'];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(PagePost::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'page_followers')->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
