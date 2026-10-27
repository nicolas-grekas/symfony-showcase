<?php

namespace App\MessageHandler;

use App\Message\EmbedProposal;
use App\Repository\ProposalRepository;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EmbedProposalHandler
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly EntityIndexer $indexer,
    ) {
    }

    public function __invoke(EmbedProposal $message): void
    {
        if ($proposal = $this->proposals->find($message->proposalId)) {
            $this->indexer->index($proposal);
        }
    }
}
