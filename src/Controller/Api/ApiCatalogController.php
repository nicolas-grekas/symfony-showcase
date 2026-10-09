<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\WebLink\JsonLinksetSerializer;
use Symfony\Component\WebLink\Link;

/**
 * Lets clients discover the API (RFC 9727).
 */
final class ApiCatalogController extends AbstractController
{
    #[Route('/.well-known/api-catalog', name: 'api_catalog', methods: ['GET'])]
    public function catalog(JsonLinksetSerializer $linksetSerializer): Response
    {
        $catalog = $this->generateUrl('api_catalog', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $api = $this->generateUrl('api_conferences', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response($linksetSerializer->serialize([
            (new Link('item', $api))->withAttribute('anchor', $catalog),
            (new Link('service-doc', $this->generateUrl('api_docs', [], UrlGeneratorInterface::ABSOLUTE_URL)))->withAttribute('anchor', $api)->withAttribute('type', 'text/html'),
        ], \JSON_UNESCAPED_SLASHES), headers: ['Content-Type' => 'application/linkset+json; profile="https://www.rfc-editor.org/info/rfc9727"']);
    }

    #[Route('/api/docs', name: 'api_docs', methods: ['GET'])]
    public function docs(): Response
    {
        return $this->render('api/docs.html.twig');
    }
}
