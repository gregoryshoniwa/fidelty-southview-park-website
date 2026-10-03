<?php

namespace App\Filament\Resources\CommunityPages\Pages;

use App\Filament\Resources\CommunityPages\CommunityPageResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityPages extends ListRecords
{
    protected static string $resource = CommunityPageResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
