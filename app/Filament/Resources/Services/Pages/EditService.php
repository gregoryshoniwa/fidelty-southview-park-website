<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return []; // Deleting a service would cascade-delete its requests. Disable it instead.
    }

    protected function afterSave(): void
    {
        app(\App\Services\AssistantService::class)->rebuildKnowledge();
    }
}
