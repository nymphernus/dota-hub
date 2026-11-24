<?php

namespace App\Exceptions;

use RuntimeException;

class PlayerNotFoundException extends RuntimeException
{
    public function __construct(?string $game = null)
    {
        parent::__construct(__('ui.player_not_found_message'));
    }
}
