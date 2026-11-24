<?php

namespace App\Exceptions;

use RuntimeException;

class MissingApiKeyException extends RuntimeException
{
    public function __construct(string $service, string $envVariable)
    {
        parent::__construct(
            __('ui.missing_api_key', ['service' => $service, 'env' => $envVariable])
        );
    }
}
