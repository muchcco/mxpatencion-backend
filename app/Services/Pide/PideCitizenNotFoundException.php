<?php

namespace App\Services\Pide;

use RuntimeException;

class PideCitizenNotFoundException extends RuntimeException
{
    public function __construct(
        string $message = 'El ciudadano no fue encontrado en PIDE.',
        public readonly bool $consumeCupo = true,
    ) {
        parent::__construct($message);
    }
}
