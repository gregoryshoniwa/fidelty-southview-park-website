<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Notices are followed on the WhatsApp channel now: reword seeded content that offered SMS subscription.
    public function up(): void
    {
        $swap = function (string $table, string $column, string $from, string $to): void {
            DB::table($table)->where($column, 'like', '%'.$from.'%')->get(['id', $column])
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)->update([$column => str_replace($from, $to, $row->{$column})]));
        };
        $swap('faqs', 'answer', 'Subscribe to SMS notices and we will tell you the day it opens.', 'We will announce the day it opens on the notice board.');
        $swap('notices', 'body', 'Subscribe to SMS notices to hear the day it opens.', 'We will announce the day it opens on the notice board.');
        $swap('services', 'summary', 'dated and signed by a committee role, with SMS alerts.', 'dated and signed by a committee role.');
        $swap('services', 'body', ' Subscribe by SMS from the notices page.', '');

        DB::table('sponsorships')->where('slot', 'half_page')->where('headline', 'Official notices by SMS')->get(['id', 'click_url'])
            ->each(fn ($ad) => DB::table('sponsorships')->where('id', $ad->id)->update([
                'headline' => 'Official notices, dated and signed', 'body' => 'One calm, official channel. No group chats.',
                'cta_label' => 'Read the notices', 'click_url' => str_replace('/notices#subscribe', '/notices', (string) $ad->click_url),
            ]));
    }

    public function down(): void
    {
        // Wording only; nothing to restore.
    }
};
