<?php

namespace App\Filament\Resources\Sponsorships\Pages;

use App\Filament\Resources\Sponsorships\SponsorshipResource;
use Filament\Resources\Pages\EditRecord;

class EditSponsorship extends EditRecord
{
    protected static string $resource = SponsorshipResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\DeleteAction::make()];
    }
}
