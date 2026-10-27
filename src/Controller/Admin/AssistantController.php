<?php

namespace App\Controller\Admin;

use App\Assistant\ReviewAssistant;
use App\Assistant\SimilarProposals;
use App\Entity\Proposal;
use App\Security\ProposalVoter;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The review assistant of a proposal, loaded in a frame of the page of the proposal.
 */
#[Route('/admin/proposals/{id}/assistant', requirements: ['id' => Requirement::UUID_V7])]
#[IsGranted(ProposalVoter::REVIEW, 'proposal')]
final class AssistantController extends AbstractController
{
    public function __construct(
        private readonly ReviewAssistant $assistant,
        private readonly SimilarProposals $similarProposals,
    ) {
    }

    #[Route('', name: 'admin_proposal_assistant', methods: ['GET'])]
    public function show(Proposal $proposal): Response
    {
        return $this->renderAssistant($proposal);
    }

    #[Route('', name: 'admin_proposal_review', methods: ['POST'])]
    #[IsCsrfTokenValid('assistant')]
    public function review(Proposal $proposal): Response
    {
        try {
            $this->assistant->review($proposal);
        } catch (PlatformException|AgentException $e) {
            return $this->renderAssistant($proposal, $e->getMessage());
        }

        return $this->redirectToRoute('admin_proposal_assistant', ['id' => $proposal->id], Response::HTTP_SEE_OTHER);
    }

    private function renderAssistant(Proposal $proposal, ?string $error = null): Response
    {
        return $this->render('admin/assistant.html.twig', [
            'proposal' => $proposal,
            'similar' => $this->similarProposals->find($proposal),
            'notes' => $this->assistant->getNotes($proposal),
            'assistant' => $this->assistant,
            'error' => $error,
        ], new Response(status: $error ? Response::HTTP_BAD_GATEWAY : Response::HTTP_OK));
    }
}
