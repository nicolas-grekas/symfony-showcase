<?php

namespace App\Controller\Api;

use App\Api\ConferenceResource;
use App\Api\TalkResource;
use App\Entity\Conference;
use App\Entity\Proposal;
use App\Repository\ConferenceRepository;
use App\Repository\ProposalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\TypeInfo\Type;

#[Route('/api/conferences', format: 'json')]
#[Cache(maxage: 300, public: true)]
final class ConferenceApiController extends AbstractController
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    #[Route('', name: 'api_conferences', methods: ['GET'])]
    public function list(ConferenceRepository $conferences): JsonResponse
    {
        $resources = array_map(fn (Conference $conference) => $this->objectMapper->map($conference, ConferenceResource::class), $conferences->findUpcoming($this->now()));

        return $this->json($resources, headers: ['Link-Template' => '"/api/conferences/{slug}"; rel="item"'], context: ['groups' => ['list']]);
    }

    #[Route('/{slug:conference}', name: 'api_conference', methods: ['GET'])]
    public function show(Conference $conference, ProposalRepository $proposals): JsonResponse
    {
        $resource = $this->objectMapper->map($conference, ConferenceResource::class);
        $resource->talks = array_map(fn (Proposal $proposal) => $this->objectMapper->map($proposal, TalkResource::class), $proposals->findAcceptedForConference($conference->slug));

        return $this->json($resource, context: ['groups' => ['detail']]);
    }

    /**
     * Streams the talks as they are read from the database, with code generated for TalkResource at cache warmup.
     */
    #[Route('/{slug:conference}/talks', name: 'api_conference_talks', methods: ['GET'])]
    public function talks(Conference $conference, ProposalRepository $proposals, JsonStreamWriter $streamWriter): StreamedResponse
    {
        $talks = (function () use ($conference, $proposals) {
            foreach ($proposals->findAcceptedForConference($conference->slug) as $proposal) {
                yield $this->objectMapper->map($proposal, TalkResource::class);
            }
        })();

        // CDNs keep the program longer than browsers do
        return new StreamedResponse($streamWriter->write($talks, Type::iterable(Type::object(TalkResource::class), Type::int())), headers: ['Content-Type' => 'application/json', 'CDN-Cache-Control' => 'max-age=3600']);
    }
}
