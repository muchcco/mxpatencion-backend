<?php

namespace App\DTOs;

final class AuthenticatedUserData
{
    public function __construct(
        public readonly string $idUsuarioExterno,
        public readonly string $loginUsuario,
        public readonly string $nombreUsuario,
        public readonly ?string $email,
        public readonly array $roles,
        public readonly array $permissions,
        public readonly bool $isAdminApp,
        public readonly array $paths,
        public readonly array $pathIds,
        public readonly ?int $idRegion,
        public readonly ?string $regionCodigo,
    ) {
    }

    public static function fromSession(array $sessionData): self
    {
        return new self(
            idUsuarioExterno: (string) $sessionData['id_usuario_externo'],
            loginUsuario: (string) $sessionData['login_usuario'],
            nombreUsuario: (string) $sessionData['nombre_usuario'],
            email: $sessionData['email'] ?? null,
            roles: self::normalizeArray($sessionData['roles'] ?? []),
            permissions: self::normalizeArray($sessionData['permissions'] ?? []),
            isAdminApp: (bool) ($sessionData['is_admin_app'] ?? false),
            paths: self::normalizeArray($sessionData['paths'] ?? []),
            pathIds: self::normalizeArray($sessionData['path_ids'] ?? []),
            idRegion: isset($sessionData['id_region']) ? (int) $sessionData['id_region'] : null,
            regionCodigo: $sessionData['region'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id_usuario_externo' => $this->idUsuarioExterno,
            'login_usuario' => $this->loginUsuario,
            'nombre_usuario' => $this->nombreUsuario,
            'email' => $this->email,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
            'is_admin_app' => $this->isAdminApp,
            'paths' => $this->paths,
            'path_ids' => $this->pathIds,
            'id_region' => $this->idRegion,
            'region' => $this->regionCodigo,
        ];
    }

    private static function normalizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($item) => $item !== null && $item !== ''));
    }
}
