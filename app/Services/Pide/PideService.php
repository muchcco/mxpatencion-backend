<?php

namespace App\Services\Pide;

class PideService
{
    public function consultarCiudadano(string $tipoDocumento, string $numeroDocumento): array
    {
        $baseUrl = config('services.pide.base_url');

        if ($baseUrl === null || $baseUrl === '') {
            throw new PideUnavailableException('PIDE no está disponible. Puede registrar al ciudadano manualmente.');
        }

        throw new PideUnavailableException('PIDE no está disponible. Puede registrar al ciudadano manualmente.', true);
    }
}
