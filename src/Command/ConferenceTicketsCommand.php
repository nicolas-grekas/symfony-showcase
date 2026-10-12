<?php

namespace App\Command;

use App\Enum\TicketStatus;
use App\Repository\ConferenceRepository;
use App\Repository\TicketRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('conference:tickets', description: 'List the tickets of a conference')]
final class ConferenceTicketsCommand
{
    public function __construct(
        private readonly ConferenceRepository $conferences,
        private readonly TicketRepository $tickets,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The slug of the conference')] string $slug,
        #[Option(description: 'Only the tickets with these statuses: pending, paid or expired')] TicketStatus ...$status,
    ): int {
        if (!$conference = $this->conferences->findOneBy(['slug' => $slug])) {
            $io->error(\sprintf('No conference has the slug "%s".', $slug));

            return Command::FAILURE;
        }

        $rows = [];
        foreach ($this->tickets->findBy(['conference' => $conference] + ($status ? ['status' => $status] : []), ['id' => 'ASC']) as $ticket) {
            // the id of a ticket tells when it was ordered
            $rows[] = [(string) $ticket->id, $ticket->id->getDateTime()->format('Y-m-d H:i'), $ticket->buyer->email, $ticket->status->value];
        }

        $io->table(['Ticket', 'Ordered at', 'Buyer', 'Status'], $rows);

        return Command::SUCCESS;
    }
}
