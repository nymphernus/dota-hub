<?php

namespace App\Http\Controllers;

use App\Models\PlayerSearch;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $recentSearches = PlayerSearch::query()
            ->recent()
            ->limit((int) config('stats.recent_searches', 8))
            ->get();

        return view('home', [
            'recentSearches' => $recentSearches,
        ]);
    }
}
