<?php

namespace App\Twig\Components;

use App\Assistant\SimilarProposal;
use App\Assistant\SimilarProposals;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Searches the talks and proposals of all conferences by the meaning of a text, as the reviewer types it.
 */
#[AsLiveComponent]
#[IsGranted('ROLE_REVIEWER')]
final class SemanticSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    public function __construct(
        private readonly SimilarProposals $similarProposals,
    ) {
    }

    /**
     * @return list<SimilarProposal>
     */
    public function getResults(): array
    {
        // a word or two is enough to mean something
        return 3 > mb_strlen(trim($this->query)) ? [] : $this->similarProposals->search($this->query);
    }
}
