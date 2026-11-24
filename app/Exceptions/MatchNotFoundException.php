<?php

namespace App\Exceptions;

use RuntimeException;

class MatchNotFoundException extends RuntimeException
{
    public function __construct(public readonly ?int $matchId = null)
    {
        parent::__construct(__('ui.match_not_found'));
    }
}
