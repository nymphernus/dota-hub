<?php

namespace App\Http\Controllers;

use App\Models\PlayerSearch;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $limit = (int) config('stats.recent_searches', 5);

        // Only Dota 2 searches are supported now. Rows left over from older
        // versions of the site are deliberately hidden.
        $recentSearches = PlayerSearch::query()
            ->where('game', PlayerSearch::GAME_DOTA)
            ->recent()
            ->limit(50)
            ->get()
            ->unique(fn (PlayerSearch $search) => $search->identifier)
            ->take($limit)
            ->values();

        return view('home', [
            'recentSearches' => $recentSearches,
        ]);
    }
}
