<?php

namespace App\Filament\Resources\CommunityPages\Pages;

use App\Filament\Resources\CommunityPages\CommunityPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunityPage extends CreateRecord
{
    protected static string $resource = CommunityPageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
