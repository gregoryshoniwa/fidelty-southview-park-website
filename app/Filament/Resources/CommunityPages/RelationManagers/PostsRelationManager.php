<?php

namespace App\Filament\Resources\CommunityPages\RelationManagers;

use App\Filament\Support\Uploads;
use App\Models\AuditLog;
use App\Models\PagePost;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    protected static ?string $title = 'Posts';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('reported_count', 'desc')
            ->columns([
                ImageColumn::make('image_path')->label('')->disk(Uploads::DISK)->height(40),
                TextColumn::make('body')->limit(120)->wrap()->searchable(),
                TextColumn::make('reported_count')->label('Reports')->sortable()->badge()
                    ->color(fn ($s) => $s > 0 ? 'danger' : 'gray'),
                TextColumn::make('published_at')->dateTime('j M Y, H:i')->sortable()->placeholder('-'),
                TextColumn::make('hidden_at')->label('Hidden')->since()->placeholder('Visible')
                    ->description(fn (PagePost $r) => $r->hidden_reason),
            ])
            ->filters([
                TernaryFilter::make('hidden_at')->label('Hidden')->nullable(),
                TernaryFilter::make('reported')->label('Reported')
                    ->queries(true: fn ($query) => $query->where('reported_count', '>', 0), false: fn ($query) => $query->where('reported_count', 0)),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Hide')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('danger')
                    ->visible(fn (PagePost $record) => $record->hidden_at === null)
                    ->schema([
                        TextInput::make('hidden_reason')->label('Reason')->required()->maxLength(255)
                            ->placeholder('e.g. Breaches community guidelines'),
                    ])
                    ->action(function (PagePost $record, array $data) {
                        $record->update(['hidden_at' => now(), 'hidden_reason' => $data['hidden_reason']]);
                        AuditLog::record('admin.page_post.hidden', $record, ['reason' => $data['hidden_reason']]);
                        Notification::make()->title('Post hidden')->success()->send();
                    }),
                Action::make('unhide')
                    ->label('Unhide')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (PagePost $record) => $record->hidden_at !== null)
                    ->requiresConfirmation()
                    ->action(function (PagePost $record) {
                        $record->update(['hidden_at' => null, 'hidden_reason' => null]);
                        AuditLog::record('admin.page_post.unhidden', $record);
                    }),
            ]);
    }
}
