<?php

namespace App\Filament\Resources\CommunityPages\Pages;

use App\Filament\Resources\CommunityPages\CommunityPageResource;
use Filament\Resources\Pages\EditRecord;

class EditCommunityPage extends EditRecord
{
    protected static string $resource = CommunityPageResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\DeleteAction::make()];
    }
}
