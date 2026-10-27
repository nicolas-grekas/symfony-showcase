<?php

namespace App\Mcp;

use App\Assistant\ReviewAssistant;
use App\Assistant\ReviewNotes;
use App\Assistant\SimilarProposal;
use App\Assistant\SimilarProposals;
use App\Entity\Proposal;
use App\Repository\ConferenceRepository;
use App\Repository\ProposalRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * The tools of the reviewers, which only read: they decide in the back office, with a recent authentication.
 */
final class ReviewerTools
{
    public function __construct(
        private readonly ConferenceRepository $conferences,
        private readonly ProposalRepository $proposals,
        private readonly ReviewAssistant $assistant,
        private readonly SimilarProposals $similarProposals,
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    /**
     * Lists the proposals of a conference that wait for a decision.
     *
     * @param string $conference The slug of the conference, e.g. "symfonylive-lille"
     *
     * @return array{proposals: list<ProposalResult>}
     */
    #[McpTool(name: 'list_proposals_to_review', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[IsGranted('ROLE_REVIEWER')]
    #[IsGranted('OAUTH2_SCOPE(proposals:review)')]
    public function listProposalsToReview(string $conference): array
    {
        $found = $this->conferences->findOneBy(['slug' => $conference]) ?? throw new ToolCallException(\sprintf('There is no conference "%s".', $conference));

        return ['proposals' => array_map(fn (Proposal $proposal) => $this->objectMapper->map($proposal, ProposalResult::class), $this->proposals->findToReview($found))];
    }

    /**
     * Gives the notes of the review assistant about a proposal: a summary, tags and a track, written by a model, and the similar talks and proposals of all conferences.
     *
     * @param string $proposal The id of the proposal
     *
     * @return array{notes: ReviewNotes, similar: list<SimilarProposal>}
     */
    #[McpTool(name: 'review_proposal', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[IsGranted('ROLE_REVIEWER')]
    #[IsGranted('OAUTH2_SCOPE(proposals:review)')]
    public function reviewProposal(string $proposal): array
    {
        $found = (Uuid::isValid($proposal) ? $this->proposals->find($proposal) : null) ?? throw new ToolCallException(\sprintf('There is no proposal "%s".', $proposal));

        try {
            $notes = $this->assistant->getNotes($found) ?? $this->assistant->review($found);
        } catch (PlatformException|AgentException $e) {
            throw new ToolCallException('The review assistant failed: '.$e->getMessage(), previous: $e);
        }

        return ['notes' => $notes, 'similar' => $this->similarProposals->find($found)];
    }
}
