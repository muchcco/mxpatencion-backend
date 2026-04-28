<?php

namespace App\Services\Pide;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class PideService
{
    public function consultarCiudadano(string $tipoDocumento, string $numeroDocumento): array
    {
        $baseUrl = config('services.pide.base_url');

        if ($baseUrl === null || $baseUrl === '') {
            throw new PideUnavailableException('No se configuró la URL de PIDE RENIEC.');
        }

        if (! preg_match('/^\d{8}$/', $numeroDocumento)) {
            throw new RuntimeException('La consulta RENIEC por PIDE solo acepta DNI de 8 dígitos.');
        }

        $credentials = $this->obtenerCredencialesReniec();

        try {
            $response = Http::timeout((int) config('services.pide.timeout', 15))
                ->accept('application/xml')
                ->get($baseUrl, [
                    'nuDniConsulta' => $numeroDocumento,
                    'nuDniUsuario' => $credentials['nuDniUsuario'],
                    'nuRucUsuario' => $credentials['nuRucUsuario'],
                    'password' => $credentials['password'],
                ]);
        } catch (Throwable $exception) {
            Log::warning('Error HTTP al consultar PIDE RENIEC.', [
                'dni' => $numeroDocumento,
                'message' => $exception->getMessage(),
            ]);

            throw new PideUnavailableException('No se pudo conectar con PIDE RENIEC.', true);
        }

        if (! $response->successful()) {
            Log::warning('PIDE RENIEC respondió con HTTP no exitoso.', [
                'dni' => $numeroDocumento,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new PideUnavailableException("PIDE RENIEC respondió HTTP {$response->status()}.", true);
        }

        return $this->mapearRespuestaReniec($response->body(), $tipoDocumento, $numeroDocumento);
    }

    private function obtenerCredencialesReniec(): array
    {
        try {
            $registro = DB::table('cfg.registro_pide')
                ->where('estado', 1)
                ->orderBy('idPide')
                ->first();
        } catch (Throwable $exception) {
            Log::warning('No se pudo leer la configuración PIDE RENIEC desde BD.', [
                'message' => $exception->getMessage(),
            ]);

            throw new PideUnavailableException('No se pudo leer la configuración activa de PIDE RENIEC en BD.');
        }

        if ($registro === null) {
            throw new PideUnavailableException('No se encontró configuración activa para PIDE RENIEC.');
        }

        return [
            'nuDniUsuario' => (string) $registro->nuDniUsuario,
            'nuRucUsuario' => (string) $registro->nuRucUsuario,
            'password' => (string) $registro->password,
        ];
    }

    private function mapearRespuestaReniec(string $xml, string $tipoDocumento, string $numeroDocumento): array
    {
        try {
            $envelope = new SimpleXMLElement($xml);
            $nodes = $envelope->xpath('//*[local-name()="return"]');
        } catch (Throwable) {
            throw new PideUnavailableException('PIDE devolvió una respuesta inválida.', true);
        }

        if ($nodes === false || $nodes === []) {
            throw new PideUnavailableException('PIDE devolvió una respuesta inválida.', true);
        }

        $result = $nodes[0];
        $codigoResultado = trim((string) ($result->coResultado ?? ''));
        $mensajeResultado = trim((string) ($result->deResultado ?? ''));

        if ($codigoResultado !== '0000') {
            throw new PideCitizenNotFoundException(
                $mensajeResultado !== '' ? $mensajeResultado : 'El ciudadano no fue encontrado en PIDE.',
                true
            );
        }

        $datos = $result->datosPersona ?? null;

        if ($datos === null) {
            throw new PideCitizenNotFoundException('El ciudadano no fue encontrado en PIDE.', true);
        }

        $fotoPath = $this->guardarFoto(
            numeroDocumento: $numeroDocumento,
            fotoBase64: trim((string) ($datos->foto ?? ''))
        );

        return [
            'id_tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'nombres' => trim((string) ($datos->prenombres ?? '')),
            'apellido_paterno' => trim((string) ($datos->apPrimer ?? '')),
            'apellido_materno' => trim((string) ($datos->apSegundo ?? '')),
            'direccion' => trim((string) ($datos->direccion ?? '')),
            'estado_civil' => trim((string) ($datos->estadoCivil ?? '')),
            'restriccion' => trim((string) ($datos->restriccion ?? '')),
            'ubigeo' => trim((string) ($datos->ubigeo ?? '')),
            'foto_path' => $fotoPath,
            'foto_url' => $fotoPath !== null ? Storage::disk('public')->url($fotoPath) : null,
            'fuente_origen_inicial' => 'PIDE',
            'fuente_ultima_actualizacion' => 'PIDE',
        ];
    }

    private function guardarFoto(string $numeroDocumento, string $fotoBase64): ?string
    {
        $fotoBase64 = (string) preg_replace('/\s+/', '', $fotoBase64);

        if ($fotoBase64 === '') {
            return null;
        }

        $binary = base64_decode($fotoBase64, true);

        if ($binary === false || $binary === '') {
            return null;
        }

        $path = sprintf('ciudadanos/fotos/%s.jpg', $numeroDocumento);

        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
