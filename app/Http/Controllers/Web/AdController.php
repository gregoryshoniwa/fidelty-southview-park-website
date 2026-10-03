<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdController extends Controller
{
    public function click(Sponsorship $sponsorship)
    {
        abort_unless($sponsorship->click_url && preg_match('#^https?://#i', $sponsorship->click_url), 404);
        $sponsorship->increment('clicks');

        return redirect()->away($sponsorship->click_url)->header('X-Robots-Tag', 'noindex');
    }

    /** Counted only when 50% of the unit was visible for 1 second (sent by the browser). One count per IP per unit per hour. */
    public function impressions(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:10'], 'ids.*' => ['integer']]);
        foreach (array_unique($data['ids']) as $id) {
            if (Cache::add("imp:{$id}:".sha1((string) $request->ip()), 1, 3600)) {
                Sponsorship::whereKey($id)->increment('impressions');
            }
        }

        return response()->noContent();
    }
}
