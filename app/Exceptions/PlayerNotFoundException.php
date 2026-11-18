<?php

namespace App\Exceptions;

use RuntimeException;

class PlayerNotFoundException extends RuntimeException
{
    public function __construct(public readonly ?string $game = null)
    {
        parent::__construct($game === 'cs2'
            ? 'Игрок CS2 не найден. Проверьте ссылку на Steam-профиль или SteamID64.'
            : 'Игрок Dota 2 не найден. Проверьте ссылку, SteamID64 или никнейм.');
    }
}
