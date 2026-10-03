<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Poll;
use App\Models\Resident;
use App\Models\Service;
use App\Models\Stand;
use App\Models\User;
use App\Services\RequestService;
use App\Services\VerificationService;
use Illuminate\Database\Seeder;

/** Local and testing only: demo accounts and stands. Never runs in production. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1001, 1200) as $n) {
            Stand::firstOrCreate(['stand_number' => (string) $n], ['phase' => $n < 1100 ? '1' : '2', 'source' => 'demo']);
        }

        $admin = User::firstOrCreate(['phone' => '+263770000001'], ['name' => 'Demo Chairperson', 'email' => 'admin@example.test', 'password' => 'ChangeMe!2026', 'phone_verified_at' => now()]);
        $admin->syncRoles(['committee', 'finance_admin', 'super_admin']);
        $treasurer = User::firstOrCreate(['phone' => '+263770000002'], ['name' => 'Demo Treasurer', 'email' => 'treasurer@example.test', 'password' => 'ChangeMe!2026', 'phone_verified_at' => now()]);
        $treasurer->syncRoles(['committee', 'finance_admin']);

        foreach (['marufu-attorneys' => '+263770000010', 'tn-cybertech-bank' => '+263770000011', 'fidelity-life' => '+263770000012'] as $slug => $phone) {
            $u = User::firstOrCreate(['phone' => $phone], ['name' => 'Demo '.Partner::where('slug', $slug)->value('name'), 'password' => 'Partner!2026', 'phone_verified_at' => now()]);
            $u->syncRoles(['partner_user']);
            Partner::where('slug', $slug)->first()->users()->syncWithoutDetaching([$u->id => ['role' => 'admin']]);
        }

        $res = User::firstOrCreate(['phone' => '+263771234567'], ['name' => 'Demo Resident', 'phone_verified_at' => now(), 'notification_prefs' => ['sms' => true, 'push' => true]]);
        $res->syncRoles(['resident', 'verified_resident']);
        $stand = Stand::where('stand_number', '1001')->first();
        Resident::updateOrCreate(['user_id' => $res->id], [
            'stand_id' => $stand->id, 'verification_status' => 'verified', 'verified_at' => now(), 'national_id_hash' => VerificationService::hashId('63-123456-A-12'),
            'national_id_last4' => 'A12', 'fidelity_reference' => 'FL-DEMO1001', 'phone_on_file_masked' => '•••• 567', 'consent_fidelity_at' => now(),
        ]);

        $resident = $res->fresh()->resident;
        if (! $resident->requests()->exists()) {
            $req = app(RequestService::class)->open($resident, Service::where('slug', 'title-deed-tracker')->first(), []);
            $req->update(['step' => 2]);
        }

        Poll::firstOrCreate(['question' => 'Which service should we open next?'], [
            'description' => 'Verified residents only, one vote per stand.',
            'options' => ['Home and deed loans', 'Schools hub', 'Security subscriptions', 'Community SACCO'],
            'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(14),
        ]);
    }
}
