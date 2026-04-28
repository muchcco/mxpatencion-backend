<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SsoReferenceTokenResolverService
{
    public function resolve(string $referenceToken): string
    {
        $baseUrl = rtrim((string) config('services.sso.auth_server_base_url'), '/');
        $resolveUrl = trim((string) config('services.sso.resolve_url', ''));
        $endpoint = (string) config('services.sso.resolve_endpoint', '/api/sso/resolve');
        $extraEndpoints = config('services.sso.resolve_endpoints', []);
        $secret = (string) config('services.sso.resolve_secret');
        $audience = (string) config('services.sso.audience');

        if (($baseUrl === '' && $resolveUrl === '') || $secret === '' || $audience === '') {
            throw new RuntimeException('La configuracion SSO para resolver reference tokens esta incompleta.');
        }

        $endpoints = [$endpoint];
        if (is_array($extraEndpoints) && $extraEndpoints !== []) {
            foreach ($extraEndpoints as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $endpoints[] = trim($candidate);
                }
            }
        }

        $defaultFallbackEndpoints = [
            '/api/sso/resolve',
            '/api/v1/sso/resolve',
        ];
        $endpoints = array_values(array_unique(array_merge($endpoints, $defaultFallbackEndpoints)));

        $urlsToTry = [];
        if ($resolveUrl !== '') {
            $urlsToTry[] = $resolveUrl;
        }

        foreach ($endpoints as $candidateEndpoint) {
            if (str_starts_with($candidateEndpoint, 'http://') || str_starts_with($candidateEndpoint, 'https://')) {
                $urlsToTry[] = $candidateEndpoint;
                continue;
            }

            if ($baseUrl !== '') {
                $urlsToTry[] = $baseUrl.'/'.ltrim($candidateEndpoint, '/');
            }
        }
        $urlsToTry = array_values(array_unique($urlsToTry));

        Log::info('Resolviendo reference token en auth-server.', [
            'auth_server_base_url' => $baseUrl,
            'resolve_url' => $resolveUrl !== '' ? $resolveUrl : null,
            'resolve_endpoints' => $endpoints,
            'urls_to_try' => $urlsToTry,
            'audience' => $audience,
        ]);

        $attempts = [];
        foreach ($urlsToTry as $url) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout((int) config('services.sso.timeout', 15))
                    ->withHeaders([
                        'X-SSO-RESOLVE-SECRET' => $secret,
                    ])
                    ->post($url, [
                        'rt' => $referenceToken,
                        'aud' => $audience,
                        'include_payload' => true,
                    ]);

                $attempts[] = sprintf(
                    '%s => HTTP %s',
                    $url,
                    $response->status()
                );

                if ($response->failed()) {
                    Log::warning('Intento de resolucion de reference token fallido.', [
                        'url' => $url,
                        'status' => $response->status(),
                        'response_body_preview' => mb_substr($response->body(), 0, 400),
                    ]);
                    continue;
                }

                $token = $response->json('token')
                    ?? $response->json('data.token')
                    ?? $response->json('data.jwt')
                    ?? $response->json('jwt');

                if (! is_string($token) || trim($token) === '') {
                    Log::warning('Intento de resolucion de reference token sin JWT valido.', [
                        'url' => $url,
                        'status' => $response->status(),
                        'response_body_preview' => mb_substr($response->body(), 0, 400),
                    ]);
                    $attempts[] = sprintf('%s => respuesta sin token JWT', $url);
                    continue;
                }

                Log::info('Reference token resuelto correctamente.', [
                    'url' => $url,
                ]);

                return trim($token);
            } catch (Throwable $throwable) {
                $attempts[] = sprintf('%s => excepcion %s', $url, $throwable->getMessage());
                Log::warning('Intento de resolucion de reference token lanzo excepcion.', [
                    'url' => $url,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        throw new RuntimeException(
            'No fue posible resolver el reference token en el auth-server. Intentos: '.implode(' | ', $attempts)
        );
    }
}
