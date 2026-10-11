<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tells clients where to get an access token the API accepts (RFC 9728).
 */
final class ProtectedResourceMetadataController extends AbstractController
{
    #[Route('/.well-known/oauth-protected-resource', name: 'api_protected_resource_metadata', methods: ['GET'])]
    public function metadata(Request $request, #[Autowire(env: 'OIDC_ISSUER')] string $issuer): JsonResponse
    {
        return (new JsonResponse([
            'resource' => $request->getSchemeAndHttpHost(),
            'authorization_servers' => [$issuer],
            'scopes_supported' => ['proposals:write'],
            'bearer_methods_supported' => ['header'],
        ]))->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_SLASHES);
    }
}
