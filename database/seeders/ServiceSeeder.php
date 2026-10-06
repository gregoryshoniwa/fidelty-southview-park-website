<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $p = Partner::pluck('id', 'slug');
        $s = [
            ['verify-me', 'Verify Me', 'shield-check', 1, 'fidelity-life', 'none', 0, 'Prove you own a stand using Fidelity Life records. One ID number, one code to your phone, two minutes.',
                '<p>Verification links your account to your stand. We send your national ID number and stand number to Fidelity Life, who confirm the match and send a one-time code to the phone number on your Agreement of Sale.</p><h2>What we keep</h2><ul><li>Your stand number and the result of the match.</li><li>A one-way scrambled copy of your ID number and its last four characters, so the same ID cannot verify two accounts.</li></ul><p>We never store your full ID number. You can ask us to delete your data at any time from Settings.</p>'],
            ['my-agreement', 'My Agreement and Payments', 'file-text', 1, 'fidelity-life', 'none', 0, 'Download your payment history and a digital Agreement of Sale. Lost the original? Request a replacement.',
                '<p>Once verified, you can download a digital copy of your Agreement of Sale and see every payment Fidelity Life has recorded against your stand.</p><h2>Lost or damaged agreement</h2><p>Request a certified replacement from the app. Fidelity Life prints it and tells you when it is ready to collect. Any fee Fidelity Life charges is shown before you confirm.</p>'],
            ['title-deed-tracker', 'Title Deed Tracker', 'landmark', 1, 'marufu-attorneys', 'none', 0, 'Upload your documents once, see your deed move step by step, and message the lawyers on your file.',
                '<p>Five law firms are processing title deeds for Southview Park residents: Marufu Attorneys, V.S. Nyangulu &amp; Associates, Mudimu &amp; Maguranyanga, Diza Attorneys and Sinyoro &amp; Partners. Choose the firm handling yours, open your deed file once, upload your Agreement of Sale and ID, and follow six clear steps:</p><ol><li>Documents received</li><li>Fidelity Life balance confirmed</li><li>Legal fees paid</li><li>Council rates clearance</li><li>Lodged at the Deeds Registry</li><li>Deed issued</li></ol><p>Your law firm updates your file from its portal and you are notified at every step. You can message them about your file directly from the app.</p>'],
            ['pay-bills', 'Pay Bills', 'credit-card', 1, 'tn-cybertech-bank', 'none', 0, 'Council rates, ZESA tokens, airtime and school fees in one place, with a receipt you can find later.',
                '<p>Pay City of Harare rates, buy ZESA tokens and airtime, and pay partner school fees through TN CyberTech Bank. Every fee is shown before you confirm, and every receipt is saved to your account.</p>'],
            ['notice-board', 'Notice Board', 'megaphone', 1, null, 'none', 0, 'One official place for notices, dated and signed by a committee role, with SMS alerts.',
                '<p>Official notices only: urgent, deeds, services, finance, events and security. Each is dated and signed by the committee role that published it. Subscribe by SMS from the notices page.</p>'],
            ['admin-inbox', 'Write to the Committee', 'inbox', 1, null, 'none', 0, 'Raise a problem or ask a question privately and get a reference number. Target reply time 48 hours.',
                '<p>The committee inbox is private. Other residents cannot see your message. You get a reference number and a reply within 48 hours. We publish only the number of open and answered requests, never their content.</p>'],
            ['polls', 'Polls and Voting', 'vote', 2, null, 'none', 0, 'Verified residents vote on community questions, one vote per stand, results published.',
                '<p>One vote per verified stand. Results are published with the number of voters.</p>'],
            ['home-and-deed-loans', 'Home and Deed Loans', 'banknote', 2, 'tn-cybertech-bank', 'partner_paid', 0, 'Finish your deed, or borrow against it to build. Pre-qualified because your stand is already verified.',
                '<p>TN CyberTech Bank is designing two loans for residents: a deed completion loan and a home improvement loan secured on your title deed. The bank is the lender and makes every decision. The association refers verified residents and is paid an introducer fee by the bank, which is disclosed on your application.</p>'],
            ['schools', 'Schools Hub', 'graduation-cap', 2, null, 'percent', 0, 'Pay fees, see reports and notices from partner schools near the estate.',
                '<p>We are approaching schools that residents\' children attend. Until a school signs, it appears in the community directory as a public listing only.</p>'],
            ['community-directory', 'Businesses, Churches and Schools', 'store', 2, null, 'none', 0, 'Follow pages, buy from neighbours and find schools, all inside the estate\'s own platform.',
                '<p>Local businesses and churches can run their own page, post updates to their followers and sell through the platform. Schools near the estate are listed for reference.</p>'],
            ['safety-and-security', 'Safety and Security', 'siren', 3, null, 'none', 0, 'Report incidents, join the Neighbourhood Watch, and subscribe to licensed rapid-response companies.',
                '<p>Report incidents privately, follow the police post project and, once partners sign, subscribe to a licensed rapid-response company.</p>'],
            ['community-sacco', 'Community SACCO', 'piggy-bank', 3, null, 'none', 0, 'Save together and borrow from the pool at fair rates, as a separately registered co-operative.',
                '<p>A savings and credit co-operative owned by its members, registered separately under the Co-operative Societies Act. The platform will be its channel only.</p>'],
        ];
        foreach ($s as $i => [$slug, $name, $icon, $phase, $partner, $feeType, $fee, $summary, $body]) {
            Service::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'icon' => $icon, 'phase' => $phase, 'partner_id' => $partner ? $p[$partner] ?? null : null,
                'fee_type' => $feeType, 'fee_amount' => $fee, 'summary' => $summary, 'body' => $body, 'sort' => $i, 'enabled' => true,
                'form_schema' => $slug === 'home-and-deed-loans' ? [
                    ['name' => 'product', 'label' => 'Which loan', 'type' => 'select', 'options' => ['Deed completion loan', 'Home improvement loan'], 'required' => true],
                    ['name' => 'amount', 'label' => 'Amount you need (US$)', 'type' => 'number', 'required' => true],
                    ['name' => 'purpose', 'label' => 'What it is for', 'type' => 'textarea', 'required' => false],
                ] : null,
            ]);
        }
    }
}
