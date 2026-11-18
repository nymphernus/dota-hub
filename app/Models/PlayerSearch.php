<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlayerSearch extends Model
{
    public const GAME_DOTA = 'dota';

    public const GAME_CS2 = 'cs2';

    public const GAMES = [
        self::GAME_DOTA => 'Dota 2',
        self::GAME_CS2 => 'CS2',
    ];

    protected $fillable = [
        'game',
        'identifier',
        'nickname',
        'avatar',
        'search_count',
        'last_searched_at',
    ];

    protected function casts(): array
    {
        return [
            'search_count' => 'integer',
            'last_searched_at' => 'datetime',
        ];
    }

    /**
     * Remember (or refresh) a successful search so it can be shown on the home page.
     */
    public static function track(string $game, string $identifier, ?string $nickname = null, ?string $avatar = null): self
    {
        $search = static::firstOrNew([
            'game' => $game,
            'identifier' => $identifier,
        ]);

        $search->nickname = $nickname ?: $search->nickname;
        $search->avatar = $avatar ?: $search->avatar;
        $search->search_count = $search->exists ? $search->search_count + 1 : 1;
        $search->last_searched_at = now();
        $search->save();

        return $search;
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('last_searched_at')->orderByDesc('id');
    }

    public function routeGame(): string
    {
        return $this->game === self::GAME_DOTA ? 'dota' : 'cs2';
    }

    public function profileUrl(): string
    {
        return $this->game === self::GAME_DOTA
            ? route('dota.player', ['accountId' => $this->identifier])
            : route('cs2.player', ['steamId' => $this->identifier]);
    }
}
