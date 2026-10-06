<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Threads\ThreadResource;
use App\Models\Thread;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestInboxThreads extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest open inbox threads')
            ->query(fn () => Thread::query()->whereNull('partner_id')->where('status', 'open')->with(['resident.user', 'resident.stand'])->latest('last_message_at'))
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->limit(5))
            ->recordUrl(fn (Thread $r) => ThreadResource::getUrl('view', ['record' => $r]))
            ->emptyStateHeading('Inbox is clear')
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('subject')->limit(60),
                TextColumn::make('category')->badge()->color('gray')->formatStateUsing(fn ($state) => Thread::CATEGORIES[$state] ?? $state),
                TextColumn::make('resident.user.name')->label('Resident')
                    ->description(fn (Thread $r) => $r->resident?->stand ? 'Stand '.$r->resident->stand->stand_number : null),
                TextColumn::make('last_message_at')->label('Last message')->since(),
            ]);
    }
}
