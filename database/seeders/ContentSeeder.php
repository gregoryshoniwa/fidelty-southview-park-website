<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\Notice;
use App\Models\Setting;
use App\Models\Sponsorship;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about' => ['About the association', 'How the Fidelity Southview Park Residents Association works and why it exists.', <<<'HTML'
<p>The Fidelity Southview Park Residents Association exists to make Southview Park, Amalinda the best-run community in Harare by delivering services residents can see working.</p>
<h2>Deliver first, ask later</h2>
<p>We do not ask for levies or a mandate. We open services one at a time, each backed in writing by a named partner, and we let residents decide whether we have earned their membership. Registering and verifying your stand makes you a member, free of charge.</p>
<h2>Five principles</h2>
<ol>
<li><strong>Deliver first.</strong> No membership drive and no levy before residents have received value.</li>
<li><strong>Nothing hidden from the committee.</strong> Every cent is written automatically to a ledger every committee member can see, with two signatories on every payment out.</li>
<li><strong>Residents own their data.</strong> Documents are stored only with consent and deleted on request.</li>
<li><strong>One channel, calm tone.</strong> Official notices are dated and signed. Disputes go to the private inbox, not a public wall.</li>
<li><strong>Partners are vetted and named.</strong> A service goes live only when a named institution stands behind it.</li>
</ol>
HTML],
            'constitution' => ['Constitution', 'The draft constitution of the Fidelity Southview Park Residents Association.', <<<'HTML'
<p><em>Draft for adoption by the founding committee. Subject to review by the association's lawyer.</em></p>
<h2>1. Name</h2><p>The association is called the Fidelity Southview Park Residents Association ("the Association").</p>
<h2>2. Nature</h2><p>The Association is a voluntary association of residents with perpetual succession, able to own property and to sue and be sued in its own name. Its benefits are exclusively for its members, and it does not solicit donations from the public.</p>
<h2>3. Objects</h2><ol><li>To deliver practical services to residents of Fidelity Southview Park, Amalinda, including help with Agreements of Sale, title deeds, bill payments, schools and security.</li><li>To represent residents to Fidelity Life Assurance, the City of Harare and other bodies.</li><li>To keep residents informed through one official channel.</li></ol>
<h2>4. Membership</h2><p>Any owner or resident of a stand who registers on the Association's platform is a member. A member whose stand is verified against Fidelity Life records may vote. One vote per stand.</p>
<h2>5. Founding committee</h2><p>A founding committee of seven serves for twelve months from adoption of this constitution. Before the term ends, verified members vote to confirm or replace each role by secret ballot on the platform.</p>
<h2>6. Finance</h2><ol><li>The Association does not charge levies. It is funded by disclosed commissions and advertising through a company it wholly owns.</li><li>Every payment out requires two of three signatories: Chairperson, Treasurer, Secretary.</li><li>Accounts are reviewed independently each year and the review is presented to members.</li></ol>
<h2>7. Conflicts of interest</h2><p>A committee member who is paid by a partner, or owns a business listed on the platform, must declare it on the conflict register before any related decision.</p>
<h2>8. Removal</h2><p>A committee member may be removed by a majority of the other members of the committee, or by a vote of verified members.</p>
<h2>9. Amendment and dissolution</h2><p>This constitution may be amended by a two-thirds majority of verified members voting. On dissolution, remaining assets pass to a community body with similar objects chosen by the members.</p>
HTML],
            'privacy' => ['Privacy and data protection', 'How we collect, use and protect your personal data under the Cyber and Data Protection Act [Chapter 12:07].', <<<'HTML'
<p>This notice explains how the Fidelity Southview Park Residents Association and its trading company ("we") handle your personal data under the Cyber and Data Protection Act [Chapter 12:07] and the Cyber and Data Protection Regulations (SI 155 of 2024).</p>
<h2>What we collect</h2><ul><li>Your name and mobile number, to create your account and send you codes and notices.</li><li>Your stand number and a one-way scrambled form of your national ID number, to verify your stand with Fidelity Life. We never store the full ID number.</li><li>Documents you choose to upload, such as your Agreement of Sale or ID, only for the service you choose.</li><li>Messages you send to the committee or to a partner.</li><li>Payment records, when online payments open.</li></ul>
<h2>Who we share it with</h2><p>Only the partner providing the service you request, and only the fields that service needs: Fidelity Life Assurance for verification and agreements, the law firm you choose for your deed file (Marufu Attorneys, V.S. Nyangulu &amp; Associates, Mudimu &amp; Maguranyanga, Diza Attorneys or Sinyoro &amp; Partners), TN CyberTech Bank for payments and loans. Each partner is bound by a written data processing agreement. If you use voice with the assistant, your audio is processed by Google's Gemini service; we ask for your consent first.</p>
<h2>Your rights</h2><ul><li>See everything we hold: Settings, then Download my data.</li><li>Correct it: edit your profile or write to the committee.</li><li>Delete it: Settings, then Delete my account. We delete your documents and anonymise your records within 30 days, keeping only what the law requires.</li><li>Withdraw consent at any time.</li></ul>
<h2>Security</h2><p>Documents are stored privately and encrypted, every view is logged, and staff use two-factor sign-in. If a breach affects you, we notify POTRAZ within 24 hours and you within 72 hours.</p>
<h2>Contact</h2><p>Write to our Data Protection Officer through the committee inbox in the app.</p>
HTML],
            'terms' => ['Terms of use', 'The rules for using the Southview Park residents platform.', <<<'HTML'
<h2>Using the platform</h2><p>You may use the platform if you live in or own a stand in Fidelity Southview Park, or if you run a partner organisation. Keep your phone secure: anyone with your one-time codes can act as you.</p>
<h2>Services</h2><p>Each service is provided by the named partner. The association connects you to the partner and shows every fee before you pay. The partner is responsible for the service itself.</p>
<h2>Community pages and messages</h2><p>No politics, personal attacks, unverified claims about individuals, or selling outside the shops section. The Secretary may hide content that breaks these rules, and every moderation decision is logged.</p>
<h2>Refunds</h2><p>Where a partner cannot deliver a paid service, the payment is refunded through the bank. Electronic purchases from shops may be cancelled within 7 days where the Consumer Protection Act allows.</p>
<h2>Changes</h2><p>We announce changes to these terms on the notice board 14 days before they take effect.</p>
HTML],
            'complaints' => ['Complaints policy', 'How to complain and how quickly we respond.', <<<'HTML'
<ol><li>Write to the committee from the app and choose "Complaint". You get a reference number straight away.</li><li>We acknowledge within 48 hours and aim to resolve within 14 days.</li><li>If you are not satisfied, ask for the complaint to go to the full committee, which decides within 30 days.</li><li>We publish the number of open and closed complaints every month, never their content.</li></ol>
HTML],
            'advertise' => ['Advertise with us', 'Advertising on the Southview Park residents platform.', <<<'HTML'
<p>Every verified household in Southview Park uses this platform for their documents, deeds and notices. Advertising here is local, calm and clearly labelled.</p><p>Income from advertising funds the association's services and is recorded like every other income.</p>
HTML],
        ];
        foreach ($pages as $slug => [$title, $meta, $body]) {
            CmsPage::updateOrCreate(['slug' => $slug], ['title' => $title, 'meta_description' => $meta, 'body' => $body, 'published' => true]);
        }

        $faqs = [
            ['verify-me', 'What do I need to verify my stand?', 'Your national ID number, your stand number and the phone number that is on your Agreement of Sale with Fidelity Life. The code goes to that phone.'],
            ['verify-me', 'My phone number has changed since I bought the stand. What now?', 'Write to the committee from the app. We will help you update your number with Fidelity Life first.'],
            ['verify-me', 'Does verifying cost anything?', 'No. Verification and membership are free.'],
            ['title-deed-tracker', 'Who is processing the title deeds?', 'Five law firms are processing Southview Park title deeds: Marufu Attorneys, V.S. Nyangulu & Associates, Mudimu & Maguranyanga, Diza Attorneys and Sinyoro & Partners. Your firm is on your Agreement of Sale or the letter from Fidelity Life. Choose it when you open your file in the app, then follow its progress there.'],
            ['title-deed-tracker', 'Which documents do I need for my deed?', 'Your Agreement of Sale, both sides of your national ID, proof of residence, and later a council rates clearance certificate. You upload them from the Deed Tracker.'],
            ['title-deed-tracker', 'How long does a title deed take?', 'It depends mostly on council clearance and the Deeds Registry. The tracker shows the exact step your file is on, and you can message the lawyers about it.'],
            ['pay-bills', 'When can I pay bills here?', 'Online payments open once the TN CyberTech Bank gateway is connected. Subscribe to SMS notices and we will tell you the day it opens.'],
            ['general', 'Is there a levy or membership fee?', 'No. The association does not charge levies. It is funded by small disclosed commissions and advertising.'],
            ['general', 'Who can see my messages to the committee?', 'Only the committee members handling your request. Other residents cannot see them.'],
            ['general', 'How do I delete my account?', 'In the app go to Settings, then Delete my account. We delete your documents and anonymise your records within 30 days.'],
            ['general', 'Is this the old residents committee?', 'No. This is a new association built around services. We do not represent or comment on any other body.'],
            ['my-agreement', 'I lost my Agreement of Sale. Can I get a new one?', 'Yes. After verifying, open My Agreement and request a replacement. Fidelity Life prints a certified copy and the app tells you when it is ready.'],
        ];
        foreach ($faqs as $i => [$topic, $q, $a]) {
            Faq::updateOrCreate(['question' => $q], ['answer' => $a, 'topic' => $topic, 'sort' => $i, 'published' => true]);
        }

        $notices = [
            ['Welcome to the Southview Park residents platform', 'services', 'Chairperson', 'Verify your stand with Fidelity Life records, download your Agreement of Sale, and write to the committee privately.', '<p>The association is open. Start by verifying your stand: it takes two minutes and needs your ID number, your stand number and the phone on your Agreement of Sale.</p><p>There is no levy and no membership fee.</p>', true, 1],
            ['Title deed files now open with all five law firms', 'deeds', 'Secretary', 'Open your deed file, upload your Agreement of Sale and ID, and follow every step from your phone.', '<p>Verified residents can now open a title deed file from the app. Choose the law firm handling your deed: it will confirm receipt of your documents within 5 working days and update your file at every step.</p>', false, 2],
            ['Online bill payments are coming soon', 'services', 'Vice Chairperson', 'Council rates, ZESA and airtime payments open when the bank gateway is connected.', '<p>We are finalising the integration with TN CyberTech Bank. Until then, please continue paying bills through your usual channels. Subscribe to SMS notices to hear the day it opens.</p>', false, 3],
            ['How to reach the committee', 'services', 'Secretary', 'There is no group chat. Write to the committee privately and get a reference number.', '<p>We have chosen not to run a WhatsApp group. Every message to the committee is private, gets a reference number and a reply within 48 hours.</p>', false, 4],
        ];
        foreach ($notices as [$title, $cat, $role, $excerpt, $body, $pinned, $daysAgo]) {
            Notice::updateOrCreate(['slug' => Str::slug($title)], [
                'title' => $title, 'category' => $cat, 'signed_by_role' => $role, 'excerpt' => $excerpt, 'body' => $body,
                'pinned' => $pinned, 'published_at' => now()->subDays($daysAgo),
            ]);
        }

        // House adverts: the association's own messages fill every ad slot until sponsors are sold.
        $house = 'Southview Park Residents Association';
        $ads = [
            ['hero_takeover', 'Verify your stand', null, null, '/app/verify', 'Verify'],
            ['billboard', 'Your stand. Your documents. Two minutes.', 'Verify with Fidelity Life records and download your Agreement of Sale from your phone.', 'images/ads/house-billboard.webp', '/app/verify', 'Verify my stand'],
            ['billboard', 'Track your title deed from your phone', 'Open your deed file once and follow every step with your law firm.', 'images/ads/house-billboard-deed.webp', '/services/title-deed-tracker', 'Open my deed file'],
            ['medium_rect', 'Advertise to every verified household', 'Sponsored tiles, notices and banners.', 'images/ads/house-rect.webp', '/advertise', 'See rates'],
            ['half_page', 'Official notices by SMS', 'Dated, signed and calm. No group chats.', 'images/ads/house-half.webp', '/notices#subscribe', 'Subscribe'],
            ['sponsored_tile', 'Your business here', 'Reach every verified household in Southview Park.', 'images/covers/advertise.webp', '/advertise', 'Advertise'],
        ];
        foreach ($ads as [$slot, $headline, $body, $creative, $url, $cta]) {
            Sponsorship::updateOrCreate(['slot' => $slot, 'headline' => $headline], [
                'advertiser' => $house, 'body' => $body, 'creative_path' => $creative, 'click_url' => url($url), 'cta_label' => $cta,
                'starts_on' => today()->subDay(), 'ends_on' => today()->addYears(2), 'price' => 0, 'status' => 'active',
            ]);
        }

        Setting::put('potraz_licence', 'application in progress');
    }
}
