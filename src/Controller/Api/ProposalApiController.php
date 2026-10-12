<?php

namespace App\Controller\Api;

use App\Api\TalkResource;
use App\Cfp\ProposalDraft;
use App\Cfp\ProposalSubmitter;
use App\Entity\Conference;
use App\Entity\Proposal;
use App\Entity\User;
use App\Security\ProposalVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(format: 'json')]
final class ProposalApiController extends AbstractController
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    /**
     * Takes the same data as the form of the call for papers, validated as a whole.
     */
    #[Route('/api/conferences/{slug:conference}/proposals', name: 'api_proposal_submit', methods: ['POST'])]
    #[IsGranted('SCOPE_proposals:write')]
    public function submit(
        Conference $conference,
        #[MapRequestPayload(validationGroups: ['Default', 'speaker', 'talk', 'materials'])] ProposalDraft $draft,
        #[CurrentUser] User $user,
        ProposalSubmitter $submitter,
    ): JsonResponse {
        if (!$conference->isCfpOpen($this->now())) {
            throw new GoneHttpException('The call for papers is closed.');
        }

        $proposal = $submitter->submit($conference, $draft, $user);

        return $this->json($this->objectMapper->map($proposal, TalkResource::class), JsonResponse::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_proposal', ['id' => $proposal->id]),
        ]);
    }

    #[Route('/api/proposals/{id}', name: 'api_proposal', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    #[IsGranted(ProposalVoter::VIEW, 'proposal', statusCode: 404)]
    public function show(Proposal $proposal): JsonResponse
    {
        return $this->json($this->objectMapper->map($proposal, TalkResource::class));
    }
}
