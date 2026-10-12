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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\WebLink\Link;

#[Route('/api/conferences', format: 'json')]
#[Cache(maxage: 300, public: true)]
final class ConferenceApiController extends AbstractController
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    /**
     * @return list<Conference>
     */
    #[Route('', name: 'api_conferences', methods: ['GET'])]
    #[Serialize(context: ['groups' => ['Default']], map: ConferenceResource::class)]
    public function list(Request $request, ConferenceRepository $conferences): array
    {
        // sent as an RFC 9652 Link-Template header, since the link is a URI template
        $this->addLink($request, new Link('item', '/api/conferences/{slug}'));

        return $conferences->findUpcoming($this->now());
    }

    #[Route('/{slug:conference}', name: 'api_conference', methods: ['GET'])]
    #[Serialize(context: ['groups' => ['Default', 'detail']])]
    public function show(Conference $conference, ProposalRepository $proposals): ConferenceResource
    {
        $resource = $this->objectMapper->map($conference, ConferenceResource::class);
        $resource->talks = array_map(fn (Proposal $proposal) => $this->objectMapper->map($proposal, TalkResource::class), $proposals->findAcceptedForConference($conference->slug));

        return $resource;
    }

    /**
     * Streams the talks, mapped one by one, with code generated for TalkResource at cache warmup.
     *
     * @return list<Proposal>
     */
    #[Route('/{slug:conference}/talks', name: 'api_conference_talks', methods: ['GET'])]
    // CDNs keep the program longer than browsers do
    #[Cache(maxage: 3600, target: 'CDN')]
    #[Serialize(map: TalkResource::class, stream: true)]
    public function talks(Conference $conference, ProposalRepository $proposals): array
    {
        return $proposals->findAcceptedForConference($conference->slug);
    }
}
