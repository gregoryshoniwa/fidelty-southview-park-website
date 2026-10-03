<?php

namespace App\Filament\Resources\Notices\Pages;

use App\Filament\Resources\Notices\NoticeResource;
use App\Services\AssistantService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditNotice extends EditRecord
{
    protected static string $resource = NoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('View on site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => url('/notices/'.$this->getRecord()->slug), shouldOpenInNewTab: true)
                ->visible(fn () => $this->getRecord()->published_at?->isPast() ?? false),
            DeleteAction::make()->after(fn () => app(AssistantService::class)->rebuildKnowledge()),
        ];
    }

    protected function afterSave(): void
    {
        app(AssistantService::class)->rebuildKnowledge();
    }
}
