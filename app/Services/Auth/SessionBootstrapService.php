<?php

namespace App\Services\Auth;

use App\DTOs\AuthenticatedUserData;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SessionBootstrapService
{
    private const SESSION_KEY = 'authenticated_advisor';

    public function __construct(
        private readonly AdvisorRegionResolverService $advisorRegionResolverService,
        private readonly AdvisorIdentityService $advisorIdentityService,
    ) {
    }

    public function bootstrapFromClaims(array $claims, Session $session): AuthenticatedUserData
    {
        $idUsuarioExterno = (string) ($claims['uid'] ?? $claims['sub'] ?? '');

        if ($idUsuarioExterno === '') {
            throw new RuntimeException('El JWT SSO no contiene el claim uid o sub.');
        }

        $email = $this->normalizeNullableString($claims['email'] ?? null);
        $loginUsuario = $email
            ?? $this->normalizeNullableString($claims['preferred_username'] ?? null)
            ?? $idUsuarioExterno;
        $nombreUsuario = $this->normalizeNullableString($claims['name'] ?? null) ?? $loginUsuario;

        $resolvedRegion = [
            'id_region' => $this->resolveIntegerClaim($claims['id_region'] ?? $claims['region_id'] ?? null),
            'region' => $this->normalizeNullableString($claims['region'] ?? $claims['region_code'] ?? null),
        ];

        if ($resolvedRegion['id_region'] === null && $resolvedRegion['region'] === null) {
            $resolvedRegion = $this->advisorRegionResolverService->resolve(
                $idUsuarioExterno,
                $loginUsuario
            );
        }

        $payload = [
            'id_usuario_externo' => $idUsuarioExterno,
            'login_usuario' => $loginUsuario,
            'nombre_usuario' => $nombreUsuario,
            'email' => $email,
            'roles' => $this->normalizeArrayClaim($claims['roles'] ?? []),
            'permissions' => $this->normalizeArrayClaim($claims['permissions'] ?? []),
            'is_admin_app' => (bool) ($claims['is_admin_app'] ?? false),
            'paths' => $this->normalizeArrayClaim($claims['paths'] ?? []),
            'path_ids' => $this->normalizeArrayClaim($claims['path_ids'] ?? []),
            'id_region' => $resolvedRegion['id_region'],
            'region' => $resolvedRegion['region'],
            'authenticated_at' => now()->toIso8601String(),
        ];

        $session->put(self::SESSION_KEY, $payload);
        $session->regenerate();

        $advisor = AuthenticatedUserData::fromSession($payload);

        $this->advisorIdentityService->sync($advisor);

        Log::info('Sesion local del asesor inicializada.', [
            'id_usuario_externo' => $advisor->idUsuarioExterno,
            'login_usuario' => $advisor->loginUsuario,
            'id_region' => $advisor->idRegion,
            'session_id' => $session->getId(),
        ]);

        return $advisor;
    }

    public function getAuthenticatedAdvisor(Session $session): ?AuthenticatedUserData
    {
        $payload = $session->get(self::SESSION_KEY);

        if (! is_array($payload)) {
            return null;
        }

        return AuthenticatedUserData::fromSession($payload);
    }

    public function destroy(Session $session): void
    {
        $session->invalidate();
        $session->regenerateToken();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function resolveIntegerClaim(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeArrayClaim(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($item) => is_scalar($item) && $item !== ''));
    }
}
