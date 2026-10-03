<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagePost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'hidden_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CommunityPage::class, 'community_page_id');
    }
}
