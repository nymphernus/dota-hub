<?php

namespace App\Http\Controllers;

use App\Models\PlayerSearch;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $limit = (int) config('stats.recent_searches', 5);

        // The table already stores one row per game + identifier. Deduplicate by
        // account before limiting so the same player cannot occupy several slots.
        $recentSearches = PlayerSearch::query()
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
