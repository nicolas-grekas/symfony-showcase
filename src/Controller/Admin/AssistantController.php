<?php

namespace App\Controller\Admin;

use App\Assistant\ReviewAssistant;
use App\Assistant\SimilarProposals;
use App\Entity\Proposal;
use App\Security\ProposalVoter;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerEvent;
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

    /**
     * Shows the panel with empty notes, which open the stream of the notes.
     */
    #[Route('', name: 'admin_proposal_review', methods: ['POST'])]
    #[IsCsrfTokenValid('assistant')]
    public function review(Proposal $proposal): Response
    {
        return $this->renderAssistant($proposal, writing: true);
    }

    /**
     * Streams what the assistant does as Turbo Streams: the tools it calls, the notes as the model writes them, then
     * the notes it keeps. It calls the model, so it needs the token of the form, as the "Summarize" button does.
     */
    #[Route('/notes', name: 'admin_proposal_notes', methods: ['GET'])]
    #[IsCsrfTokenValid('assistant', tokenKey: 'token', tokenSource: IsCsrfTokenValid::SOURCE_QUERY)]
    public function notes(Proposal $proposal): EventStreamResponse
    {
        return new EventStreamResponse(function () use ($proposal) {
            $template = 'admin/assistant.stream.html.twig';
            $steps = $this->assistant->stream($proposal);

            try {
                foreach ($steps as $step) {
                    yield new ServerEvent($this->renderBlockView($template, $step instanceof ToolCall ? 'tool_call' : 'partial_notes', ['step' => $step]));
                }

                yield new ServerEvent($this->renderBlockView($template, 'notes', ['notes' => $steps->getReturn(), 'assistant' => $this->assistant]));
            } catch (PlatformException|AgentException $e) {
                yield new ServerEvent($this->renderBlockView($template, 'error', ['error' => $e->getMessage()]));
            }
        });
    }

    private function renderAssistant(Proposal $proposal, bool $writing = false): Response
    {
        return $this->render('admin/assistant.html.twig', [
            'proposal' => $proposal,
            'similar' => $this->similarProposals->find($proposal),
            'notes' => $writing ? null : $this->assistant->getNotes($proposal),
            'writing' => $writing,
            'assistant' => $this->assistant,
        ]);
    }
}
