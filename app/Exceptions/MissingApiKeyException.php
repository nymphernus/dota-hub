<?php

namespace App\Exceptions;

use RuntimeException;

class MissingApiKeyException extends RuntimeException
{
    public function __construct(string $service, string $envVariable)
    {
        parent::__construct(
            "Для работы с {$service} добавьте {$envVariable} в файл .env и перезапустите контейнеры."
        );
    }
}
