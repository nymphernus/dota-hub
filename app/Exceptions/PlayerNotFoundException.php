<?php

namespace App\Exceptions;

use RuntimeException;

class PlayerNotFoundException extends RuntimeException
{
    public function __construct(?string $game = null)
    {
        parent::__construct('Игрок Dota 2 не найден. Проверьте ссылку, SteamID64 или никнейм.');
    }
}
