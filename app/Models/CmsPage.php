<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    protected $guarded = ['id'];

    /** Whether a page is published, so links to it can be hidden until it is. */
    public static function live(string $slug): bool
    {
        return static::where('slug', $slug)->where('published', true)->exists();
    }

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
