<?php

namespace App\Exceptions;

use RuntimeException;

class ExternalApiException extends RuntimeException
{
    public function __construct(
        public readonly string $service,
        string $details = '',
        public readonly int $status = 502,
        ?string $customMessage = null,
    ) {
        $message = $customMessage ?? "Сервис {$service} временно недоступен.";

        if ($details !== '') {
            $message .= " {$details}";
        }

        parent::__construct($message);
    }
}
