<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'opens_at' => 'datetime', 'closes_at' => 'datetime', 'results_published_at' => 'datetime'];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function isOpen(): bool
    {
        return now()->between($this->opens_at, $this->closes_at);
    }

    public function tally(): array
    {
        $counts = $this->votes()->selectRaw('option_index, count(*) c')->groupBy('option_index')->pluck('c', 'option_index');

        return collect($this->options)->map(fn ($label, $i) => ['label' => $label, 'votes' => (int) ($counts[$i] ?? 0)])->all();
    }
}
