<?php

namespace App\Services\Auth;

use RuntimeException;

class ActiveSessionExistsException extends RuntimeException
{
    public function __construct(
        string $message = 'Ya existe una sesion activa para este usuario.',
        public readonly ?string $activeSessionId = null,
    ) {
        parent::__construct($message);
    }
}
