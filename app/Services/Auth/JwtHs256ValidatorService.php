<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class JwtHs256ValidatorService
{
    public function validate(string $jwt): array
    {
        $secret = trim((string) config('services.sso.shared_secret'));
        $expectedAudience = (string) config('services.sso.audience');
        $expectedIssuer = trim((string) config('services.sso.issuer'));
        $resolveSecretConfigured = trim((string) config('services.sso.resolve_secret')) !== '';

        if ($secret === '') {
            throw new RuntimeException('La clave compartida SSO no esta configurada.');
        }

        $jwt = $this->normalizeJwt($jwt);
        [$encodedHeader, $encodedPayload, $encodedSignature] = $this->splitToken($jwt);

        $header = $this->decodeSegment($encodedHeader, 'header');
        $payload = $this->decodeSegment($encodedPayload, 'payload');

        Log::info('JWT SSO decodificado para validacion.', [
            'token_length' => strlen($jwt),
            'alg' => $header['alg'] ?? null,
            'typ' => $header['typ'] ?? null,
            'aud_preview' => $payload['aud'] ?? null,
            'iss_preview' => $payload['iss'] ?? null,
            'uses_shared_secret' => true,
            'resolve_secret_configured' => $resolveSecretConfigured,
            'shared_secret_length' => strlen($secret),
        ]);

        if (($header['alg'] ?? null) !== 'HS256') {
            Log::warning('JWT SSO rechazado por algoritmo inesperado.', [
                'alg' => $header['alg'] ?? null,
            ]);
            throw new RuntimeException('El JWT recibido no usa el algoritmo HS256 esperado.');
        }

        $providedSignature = $this->decodeSignature($encodedSignature);
        $expectedSignature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $secret, true);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            Log::warning('JWT SSO rechazado por firma invalida.', [
                'reason' => 'invalid_signature',
                'provided_signature_length' => strlen($providedSignature),
                'expected_signature_length' => strlen($expectedSignature),
            ]);
            throw new RuntimeException('La firma del JWT SSO no es valida.');
        }

        $now = now()->timestamp;

        if (! isset($payload['exp']) || ! is_numeric($payload['exp']) || (int) $payload['exp'] < $now) {
            Log::warning('JWT SSO rechazado por exp invalido o expirado.', [
                'exp' => $payload['exp'] ?? null,
                'now' => $now,
            ]);
            throw new RuntimeException('El JWT SSO ha expirado o no contiene un exp valido.');
        }

        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && (int) $payload['nbf'] > $now) {
            Log::warning('JWT SSO rechazado por nbf futuro.', [
                'nbf' => $payload['nbf'],
                'now' => $now,
            ]);
            throw new RuntimeException('El JWT SSO aun no es valido segun el claim nbf.');
        }

        if ($expectedAudience !== '' && ! $this->audienceMatches($payload['aud'] ?? null, $expectedAudience)) {
            Log::warning('JWT SSO rechazado por audience invalida.', [
                'aud' => $payload['aud'] ?? null,
                'expected_audience' => $expectedAudience,
            ]);
            throw new RuntimeException('El JWT SSO no fue emitido para la audiencia esperada.');
        }

        if ($expectedIssuer !== '' && ($payload['iss'] ?? null) !== $expectedIssuer) {
            Log::warning('JWT SSO rechazado por issuer invalido.', [
                'iss' => $payload['iss'] ?? null,
                'expected_issuer' => $expectedIssuer,
            ]);
            throw new RuntimeException('El JWT SSO no coincide con el issuer configurado.');
        }

        Log::info('JWT SSO validado con exito.', [
            'uid' => $payload['uid'] ?? $payload['sub'] ?? null,
            'aud' => $payload['aud'] ?? null,
            'iss' => $payload['iss'] ?? null,
            'exp' => $payload['exp'] ?? null,
        ]);

        return $payload;
    }

    private function splitToken(string $jwt): array
    {
        $segments = explode('.', $jwt);

        if (count($segments) !== 3) {
            throw new RuntimeException('El JWT SSO no tiene un formato valido.');
        }

        if (in_array('', $segments, true)) {
            throw new RuntimeException('El JWT SSO no tiene un formato valido.');
        }

        return $segments;
    }

    private function normalizeJwt(string $jwt): string
    {
        if (str_starts_with($jwt, 'Bearer ')) {
            Log::warning('JWT SSO recibido con prefijo Bearer, formato no permitido en este flujo.');
            throw new RuntimeException('El JWT SSO fue enviado con un formato invalido.');
        }

        if (preg_match('/\s/', $jwt) === 1) {
            Log::warning('JWT SSO recibido con espacios o saltos de linea, formato no permitido.');
            throw new RuntimeException('El JWT SSO fue enviado con un formato invalido.');
        }

        return $jwt;
    }

    private function decodeSegment(string $segment, string $name): array
    {
        $decoded = $this->base64UrlDecode($segment);

        if ($decoded === false) {
            throw new RuntimeException("No fue posible decodificar el {$name} del JWT.");
        }

        $json = json_decode($decoded, true);

        if (! is_array($json)) {
            throw new RuntimeException("El {$name} del JWT no contiene un JSON valido.");
        }

        return $json;
    }

    private function decodeSignature(string $signature): string
    {
        $decoded = $this->base64UrlDecode($signature);

        if ($decoded === false) {
            throw new RuntimeException('La firma del JWT no tiene un formato base64url valido.');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = (4 - strlen($value) % 4) % 4;

        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);
    }

    private function audienceMatches(mixed $audienceClaim, string $expectedAudience): bool
    {
        if (is_string($audienceClaim)) {
            return hash_equals($audienceClaim, $expectedAudience);
        }

        if (is_array($audienceClaim)) {
            return in_array($expectedAudience, $audienceClaim, true);
        }

        return false;
    }
}
