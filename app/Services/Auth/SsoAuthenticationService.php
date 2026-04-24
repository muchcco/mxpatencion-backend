<?php

namespace App\Services\Auth;

use App\DTOs\AuthenticatedUserData;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SsoAuthenticationService
{
    public function __construct(
        private readonly SsoReferenceTokenResolverService $referenceTokenResolverService,
        private readonly JwtHs256ValidatorService $jwtHs256ValidatorService,
        private readonly SessionBootstrapService $sessionBootstrapService,
    ) {
    }

    public function authenticate(array $payload, Session $session): AuthenticatedUserData
    {
        $jwt = $this->resolveJwt($payload);
        Log::info('JWT SSO listo para validacion.', [
            'session_id' => $session->getId(),
            'jwt_length' => strlen($jwt),
        ]);

        $claims = $this->jwtHs256ValidatorService->validate($jwt);
        Log::info('JWT SSO validado correctamente.', [
            'uid' => $claims['uid'] ?? $claims['sub'] ?? null,
            'email' => $claims['email'] ?? null,
        ]);

        return $this->sessionBootstrapService->bootstrapFromClaims($claims, $session);
    }

    private function resolveJwt(array $payload): string
    {
        $token = isset($payload['token']) ? (string) $payload['token'] : '';
        $referenceToken = isset($payload['rt']) ? (string) $payload['rt'] : '';

        if ($token !== '') {
            Log::info('SSO callback trabajara con JWT directo.');
            return $token;
        }

        if ($referenceToken !== '') {
            Log::info('SSO callback trabajara con reference token.');
            return $this->referenceTokenResolverService->resolve($referenceToken);
        }

        throw new RuntimeException('Debe enviar un token o un reference token para iniciar la sesion SSO.');
    }
}
