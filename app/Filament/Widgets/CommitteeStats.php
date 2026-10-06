<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Residents\ResidentResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\Sponsorships\SponsorshipResource;
use App\Filament\Resources\Threads\ThreadResource;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\Sponsorship;
use App\Models\Thread;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommitteeStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $open = Thread::whereNull('partner_id')->where('status', 'open')->count();
        $unassigned = Thread::whereNull('partner_id')->where('status', 'open')->whereNull('assigned_to')->count();
        $verified = Resident::where('verification_status', 'verified')->count();
        $toCheck = ResidentResource::awaitingCommitteeCount();
        $requests = ServiceRequest::whereNotIn('status', ['closed', 'cancelled'])->count();
        $waiting = ServiceRequest::where('status', 'waiting_partner')->count();
        $ads = Sponsorship::where('status', 'active')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->count();

        return [
            Stat::make('Open inbox threads', number_format($open))
                ->description($unassigned ? $unassigned.' unassigned' : 'All assigned')
                ->icon(Heroicon::OutlinedInbox)
                ->color($open ? 'warning' : 'success')
                ->url(ThreadResource::getUrl('index')),
            Stat::make('Verified residents', number_format($verified))
                ->description($toCheck ? $toCheck.' waiting for the committee to check' : 'Nothing waiting for the committee')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color($toCheck ? 'warning' : 'success')
                ->url(ResidentResource::getUrl('index', ['filters' => ['needs_check' => ['isActive' => true]]])),
            Stat::make('Open service requests', number_format($requests))
                ->description($waiting.' with partners')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->url(ServiceRequestResource::getUrl('index')),
            Stat::make('Adverts live', number_format($ads))
                ->icon(Heroicon::OutlinedPhoto)
                ->url(SponsorshipResource::getUrl('index')),
        ];
    }
}
