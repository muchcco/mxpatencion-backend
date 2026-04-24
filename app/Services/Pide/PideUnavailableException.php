<?php

namespace App\Services\Pide;

use RuntimeException;

class PideUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'PIDE no está disponible.',
        public readonly bool $consumeCupo = false,
    ) {
        parent::__construct($message);
    }
}
