<?php

namespace App\Exceptions;

class ApiRateLimitException extends ExternalApiException
{
    public function __construct(string $service, string $details = '')
    {
        parent::__construct($service, $details, 429);
    }
}
