<?php

namespace App\Command;

use App\Entity\Proposal;
use App\Repository\ProposalRepository;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Groups the commands run as "assistant <command>", or "assistant:<command>".
 */
#[AsCommand('assistant', description: 'Run the review assistant')]
final class AssistantCommands
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly EntityIndexer $indexer,
    ) {
    }

    #[AsCommand('index', description: 'Embed the proposals for the similarity search, e.g. after a change of AI_EMBEDDING_MODEL')]
    public function index(SymfonyStyle $io, #[Option(description: 'Only embed the proposals that have no embedding')] bool $missing = false): int
    {
        $count = $missing ? $this->proposals->count(['embedding' => null]) : $this->proposals->count();

        // one call to the model per batch of 100 proposals
        $this->indexer->index(Proposal::class, ['only_missing' => $missing]);

        $io->success(\sprintf('%d %s embedded.', $count, 1 === $count ? 'proposal is' : 'proposals are'));

        return Command::SUCCESS;
    }
}
