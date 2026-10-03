<?php

namespace App\Filament\Resources\CommitteeMembers\Pages;

use App\Filament\Resources\CommitteeMembers\CommitteeMemberResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCommitteeMembers extends ManageRecords
{
    protected static string $resource = CommitteeMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
