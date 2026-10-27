<?php

namespace App\Assistant;

use App\Entity\Proposal;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\RetrieverInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Finds the talks and proposals of all conferences that are the closest to a proposal, or to the meaning of a text,
 * by the embeddings of their title and abstract.
 */
final class SimilarProposals
{
    public function __construct(
        #[Target('proposals')]
        private readonly StoreInterface $store,
        #[Target('proposals')]
        private readonly RetrieverInterface $retriever,
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
            if (\count($similar) < $limit && !$document->getEntity()->id->equals($proposal->id)) {
                $similar[] = $this->toSimilarProposal($document);
            }
        }

        return $similar;
    }

    /**
     * Embeds the text with the model of the proposals, then finds the closest ones.
     *
     * @return list<SimilarProposal>
     */
    public function search(string $text, int $limit = 5): array
    {
        $similar = [];
        foreach ($this->retriever->retrieve($text, ['limit' => $limit]) as $document) {
            $similar[] = $this->toSimilarProposal($document);
        }

        return $similar;
    }

    private function toSimilarProposal(EntityVectorDocument $document): SimilarProposal
    {
        $similar = $this->objectMapper->map($document->getEntity(), SimilarProposal::class);
        // the store gives the cosine distance
        $similar->similarity = round(1 - $document->getScore(), 2);

        return $similar;
    }
}
