<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'fidelity-life', 'name' => 'Fidelity Life Assurance', 'type' => 'developer', 'logo_path' => 'images/partners/fidelity-life.webp', 'website' => 'https://fidelitylife.co.zw',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'export'],
                'workflow_steps' => ['Request received', 'Checking records', 'Replacement printed', 'Ready for collection']],
            ['slug' => 'marufu-attorneys', 'name' => 'Marufu Attorneys', 'type' => 'law_firm', 'logo_path' => 'images/partners/marufu-attorneys.webp', 'website' => 'https://marufuattorneys.com',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'export'],
                'workflow_steps' => ['Documents received', 'Fidelity Life balance confirmed', 'Legal fees paid', 'Council rates clearance', 'Lodged at the Deeds Registry', 'Deed issued']],
            ['slug' => 'tn-cybertech-bank', 'name' => 'TN CyberTech Bank', 'type' => 'bank', 'logo_path' => 'images/partners/tn-cybertech-bank.webp', 'website' => 'https://tncybertechbank.co.zw',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'settlements', 'loans', 'export'],
                'workflow_steps' => ['Application received', 'Documents requested', 'Assessment', 'Decision', 'Funds disbursed']],
        ];
        foreach ($rows as $r) {
            Partner::updateOrCreate(['slug' => $r['slug']], $r + ['active' => true]);
        }
    }
}
