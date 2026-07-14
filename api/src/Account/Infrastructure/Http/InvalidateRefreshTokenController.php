<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Révoque un refresh token (logout côté serveur). Posséder le token EST la
 * preuve d'autorisation ; la réponse est toujours 204 pour ne pas révéler
 * l'existence (ou non) d'un token. Le token vient du cookie httpOnly (M2.0)
 * ou, à défaut, du corps (outillage/tests). La réponse EFFACE les deux
 * cookies d'auth : le logout ne laisse rien dans le navigateur.
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
        $token = \is_array($payload) && \is_string($payload['refresh_token'] ?? null) && '' !== $payload['refresh_token']
            ? $payload['refresh_token']
            : $request->cookies->get('refresh_token');

        if (\is_string($token) && '' !== $token) {
            $refreshToken = $this->refreshTokens->get($token);
            if (null !== $refreshToken) {
                $this->refreshTokens->delete($refreshToken);
            }
        }

        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie('plume_jwt', '/', null, true, true, Cookie::SAMESITE_LAX);
        $response->headers->clearCookie('refresh_token', '/api/v1/token', null, true, true, Cookie::SAMESITE_LAX);

        return $response;
    }
}
