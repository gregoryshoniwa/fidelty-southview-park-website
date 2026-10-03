<?php

namespace App\Filament\Resources\Threads\Pages;

use App\Filament\Resources\Threads\ThreadResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListThreads extends ListRecords
{
    protected static string $resource = ThreadResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'open'))
                ->badge(fn () => ThreadResource::getEloquentQuery()->where('status', 'open')->count() ?: null),
            'mine' => Tab::make('Assigned to me')->modifyQueryUsing(fn (Builder $query) => $query->where('assigned_to', auth()->id())->where('status', '!=', 'closed')),
            'answered' => Tab::make('Answered')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'answered')),
            'closed' => Tab::make('Closed')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'closed')),
            'all' => Tab::make('All'),
        ];
    }
}
