<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SsoReferenceTokenResolverService
{
    public function resolve(string $referenceToken): string
    {
        $baseUrl = rtrim((string) config('services.sso.auth_server_base_url'), '/');
        $endpoint = (string) config('services.sso.resolve_endpoint', '/api/sso/resolve');
        $secret = (string) config('services.sso.resolve_secret');
        $audience = (string) config('services.sso.audience');

        if ($baseUrl === '' || $secret === '' || $audience === '') {
            throw new RuntimeException('La configuracion SSO para resolver reference tokens esta incompleta.');
        }

        Log::info('Resolviendo reference token en auth-server.', [
            'auth_server_base_url' => $baseUrl,
            'resolve_endpoint' => $endpoint,
            'audience' => $audience,
        ]);

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.sso.timeout', 15))
            ->withHeaders([
                'X-SSO-RESOLVE-SECRET' => $secret,
            ])
            ->post($endpoint, [
                'rt' => $referenceToken,
                'aud' => $audience,
                'include_payload' => true,
            ]);

        if ($response->failed()) {
            Log::warning('Fallo la resolucion del reference token.', [
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            throw new RuntimeException('No fue posible resolver el reference token en el auth-server.');
        }

        $token = $response->json('token')
            ?? $response->json('data.token')
            ?? $response->json('data.jwt')
            ?? $response->json('jwt');

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('El auth-server no devolvio un JWT valido al resolver el reference token.');
        }

        Log::info('Reference token resuelto correctamente.');

        return trim($token);
    }
}
