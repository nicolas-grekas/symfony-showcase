<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lets clients discover the API (RFC 9727).
 */
final class ApiCatalogController extends AbstractController
{
    #[Route('/.well-known/api-catalog', name: 'api_catalog', methods: ['GET'])]
    public function catalog(): JsonResponse
    {
        $catalog = $this->generateUrl('api_catalog', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $api = $this->generateUrl('api_conferences', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // an RFC 9264 linkset, grouping the links of each context by relation type
        return $this->json(['linkset' => [
            ['anchor' => $catalog, 'item' => [['href' => $api]]],
            ['anchor' => $api, 'service-doc' => [['href' => $this->generateUrl('api_docs', [], UrlGeneratorInterface::ABSOLUTE_URL), 'type' => 'text/html']]],
        ]], headers: ['Content-Type' => 'application/linkset+json; profile="https://www.rfc-editor.org/info/rfc9727"'], context: ['json_encode_options' => \JSON_UNESCAPED_SLASHES]);
    }

    #[Route('/api/docs', name: 'api_docs', methods: ['GET'])]
    public function docs(): Response
    {
        return $this->render('api/docs.html.twig');
    }
}
