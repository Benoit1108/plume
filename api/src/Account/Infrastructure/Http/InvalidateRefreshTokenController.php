<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Révoque un refresh token (logout côté serveur). Posséder le token EST la
 * preuve d'autorisation ; la réponse est toujours 204 pour ne pas révéler
 * l'existence (ou non) d'un token.
 */
#[AsController]
final class InvalidateRefreshTokenController
{
    public function __construct(private readonly RefreshTokenManagerInterface $refreshTokens)
    {
    }

    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $token = \is_array($payload) && \is_string($payload['refresh_token'] ?? null) ? $payload['refresh_token'] : null;

        if (null !== $token && '' !== $token) {
            $refreshToken = $this->refreshTokens->get($token);
            if (null !== $refreshToken) {
                $this->refreshTokens->delete($refreshToken);
            }
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
