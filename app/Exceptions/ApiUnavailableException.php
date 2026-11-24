<?php

namespace App\Exceptions;

class ApiUnavailableException extends ExternalApiException
{
    public function __construct(string $service = 'OpenDota')
    {
        parent::__construct(
            $service,
            status: 502,
            customMessage: __('ui.api_unavailable_message', ['service' => $service]),
        );
    }
}
