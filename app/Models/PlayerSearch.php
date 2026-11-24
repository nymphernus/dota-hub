<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlayerSearch extends Model
{
    public const GAME_DOTA = 'dota';

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
    public static function track(string $identifier, ?string $nickname = null, ?string $avatar = null): self
    {
        $search = static::firstOrNew([
            'game' => self::GAME_DOTA,
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

    public function profileUrl(): string
    {
        return route('player', ['accountId' => $this->identifier]);
    }
}
