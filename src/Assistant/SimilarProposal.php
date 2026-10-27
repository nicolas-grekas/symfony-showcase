<?php

namespace App\Assistant;

use App\Entity\Proposal;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Proposal::class)]
final class SimilarProposal
{
    public Uuid $id;
    public string $title;

    #[Map(transform: [ProposalDetails::class, 'name'])]
    public string $conference;

    public string $status;

    /** The cosine similarity of the embeddings, from 0 to 1 */
    #[Map(if: false)]
    public float $similarity;
}
