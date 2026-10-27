<?php

namespace App\Assistant;

use App\Entity\Proposal;
use App\Enum\Track;
use App\Mcp\ProposalResult;
use App\Repository\ProposalRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Tool\ToolEffectHints;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * The tools of the review assistant. They only read: deciding on a proposal is a reviewer action.
 */
#[AsTool('get_proposal', 'Reads a proposal: its title, abstract, level, format, status, conference and speakers', method: 'getProposal', effectHints: new ToolEffectHints(readOnlyHint: true))]
#[AsTool('list_speaker_proposals', 'Lists the other proposals of the speakers of a proposal, at all conferences, with their status', method: 'listSpeakerProposals', effectHints: new ToolEffectHints(readOnlyHint: true))]
#[AsTool('find_similar_proposals', 'Lists the talks and proposals of all conferences whose title and abstract are the closest to those of a proposal, with their similarity from 0 to 1', method: 'findSimilarProposals', effectHints: new ToolEffectHints(readOnlyHint: true))]
#[AsTool('list_tracks', 'Lists the tracks of the conferences and what they are about', method: 'listTracks', effectHints: new ToolEffectHints(readOnlyHint: true))]
final class ReviewTools
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly SimilarProposals $similarProposals,
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    /**
     * @param string $id The id of the proposal
     */
    public function getProposal(string $id): ProposalDetails
    {
        return $this->objectMapper->map($this->find($id), ProposalDetails::class);
    }

    /**
     * @param string $id The id of the proposal
     *
     * @return list<ProposalResult>
     */
    public function listSpeakerProposals(string $id): array
    {
        $proposal = $this->find($id);
        $others = [];
        foreach ($proposal->speakers as $speaker) {
            foreach ($this->proposals->findBySpeakerEmail($speaker->email) as $other) {
                if (!$other->id->equals($proposal->id)) {
                    $others[(string) $other->id] = $this->objectMapper->map($other, ProposalResult::class);
                }
            }
        }

        return array_values($others);
    }

    /**
     * @param string $id The id of the proposal
     *
     * @return list<SimilarProposal>
     */
    public function findSimilarProposals(string $id): array
    {
        return $this->similarProposals->find($this->find($id));
    }

    /**
     * @return list<array{track: string, description: string}>
     */
    public function listTracks(): array
    {
        return array_map(static fn (Track $track) => ['track' => $track->value, 'description' => $track->describe()], Track::cases());
    }

    private function find(string $id): Proposal
    {
        return $this->proposals->find($id) ?? throw new \InvalidArgumentException(\sprintf('There is no proposal "%s".', $id));
    }
}
