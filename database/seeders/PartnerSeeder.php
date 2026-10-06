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
            // The other conveyancing firms processing Southview Park title deeds. Contacts from each firm's
            // own website or public directory listings (checked 6 Oct 2026); confirm with each firm before launch.
            ['slug' => 'vs-nyangulu', 'name' => 'V.S. Nyangulu & Associates', 'type' => 'law_firm', 'logo_path' => 'images/partners/vs-nyangulu.webp', 'website' => 'https://www.vsnlegal.com',
                'contact_email' => 'vsn@vsnlegal.com', 'contact_phone' => '+263 8677 010 011 / +263 78 314 3860', 'address' => '9 Gaynor Road, Highlands, Harare',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'export'], 'workflow_steps' => ['Documents received', 'Fidelity Life balance confirmed', 'Legal fees paid', 'Council rates clearance', 'Lodged at the Deeds Registry', 'Deed issued']],
            ['slug' => 'mudimu-maguranyanga', 'name' => 'Mudimu & Maguranyanga Legal Practitioners', 'type' => 'law_firm', 'logo_path' => 'images/partners/mudimu-maguranyanga.webp', 'website' => 'https://www.chimmlaw.co.zw',
                'contact_email' => 'info@chimmlaw.co.zw', 'contact_phone' => '+263 242 791 627 / +263 772 212 097', 'address' => '4th Floor East Wing, Takura House, 67 Kwame Nkrumah Avenue, Harare',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'export'], 'workflow_steps' => ['Documents received', 'Fidelity Life balance confirmed', 'Legal fees paid', 'Council rates clearance', 'Lodged at the Deeds Registry', 'Deed issued']],
            ['slug' => 'diza-attorneys', 'name' => 'Diza Attorneys', 'type' => 'law_firm', 'logo_path' => 'images/partners/diza-attorneys.webp', 'website' => 'https://dizaattorneys.co.zw',
                'contact_email' => 'info@dizaattorneys.co.zw', 'contact_phone' => '+263 242 746 189 / +263 716 919 221', 'address' => '4 Ainslie House, 4th Street (between Chinamano and J. Tongogara Ave), Harare',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'export'], 'workflow_steps' => ['Documents received', 'Fidelity Life balance confirmed', 'Legal fees paid', 'Council rates clearance', 'Lodged at the Deeds Registry', 'Deed issued']],
            ['slug' => 'sinyoro-and-partners', 'name' => 'Sinyoro & Partners', 'type' => 'law_firm', 'logo_path' => 'images/partners/sinyoro-and-partners.webp', 'website' => null,
                'contact_email' => null, 'contact_phone' => '+263 242 744 000 / +263 242 744 003', 'address' => '3 Ashton Road, Alexandra Park, Harare',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'export'], 'workflow_steps' => ['Documents received', 'Fidelity Life balance confirmed', 'Legal fees paid', 'Council rates clearance', 'Lodged at the Deeds Registry', 'Deed issued']],
            ['slug' => 'tn-cybertech-bank', 'name' => 'TN CyberTech Bank', 'type' => 'bank', 'logo_path' => 'images/partners/tn-cybertech-bank.webp', 'website' => 'https://tncybertechbank.co.zw',
                'modules' => ['queue', 'documents', 'status_updates', 'messages', 'broadcast', 'settlements', 'loans', 'export'],
                'workflow_steps' => ['Application received', 'Documents requested', 'Assessment', 'Decision', 'Funds disbursed']],
        ];
        foreach ($rows as $r) {
            Partner::updateOrCreate(['slug' => $r['slug']], $r + ['active' => true]);
        }
    }
}
