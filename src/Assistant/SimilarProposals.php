<?php

namespace App\Assistant;

use App\Entity\Proposal;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Finds the talks and proposals of all conferences that are the closest to a proposal, by the embeddings of their
 * title and abstract.
 */
final class SimilarProposals
{
    public function __construct(
        #[Target('proposals')]
        private readonly StoreInterface $store,
        private readonly ObjectMapperInterface $objectMapper,
    ) {
    }

    /**
     * @return list<SimilarProposal>
     */
    public function find(Proposal $proposal, int $limit = 5): array
    {
        if (null === $proposal->embedding) {
            return [];
        }

        $similar = [];
        // one more, as the closest proposal is the proposal itself
        foreach ($this->store->query(new VectorQuery($proposal->embedding), ['limit' => $limit + 1]) as $document) {
            \assert($document instanceof EntityVectorDocument);
            if (\count($similar) < $limit && !$document->getEntity()->id->equals($proposal->id)) {
                $similar[] = $result = $this->objectMapper->map($document->getEntity(), SimilarProposal::class);
                // the store gives the cosine distance
                $result->similarity = round(1 - $document->getScore(), 2);
            }
        }

        return $similar;
    }
}
