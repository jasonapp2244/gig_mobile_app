<?php

namespace App\Services;

use RuntimeException;

/** A Google Analytics problem with a status the admin page can explain. */
class AnalyticsException extends RuntimeException
{
    public function __construct(public string $status, string $message, public ?string $activationUrl = null)
    {
        parent::__construct($message);
    }
}
