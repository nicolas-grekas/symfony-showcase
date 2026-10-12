<?php

namespace App\Command;

use App\Repository\ConferenceRepository;
use App\Repository\TicketRepository;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('conference:list', description: 'List the conferences and their ticket sales')]
final class ConferenceListCommand
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ConferenceRepository $conferences,
        private readonly TicketRepository $tickets,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Include the past conferences')] bool $all = false): int
    {
        $now = $this->now();
        $rows = [];
        foreach ($all ? $this->conferences->findBy([], ['startsOn' => 'ASC']) : $this->conferences->findUpcoming($now) as $conference) {
            $rows[] = [
                $conference->slug,
                $conference->name,
                $conference->startsOn->format('Y-m-d'),
                $conference->isCfpOpen($now) ? 'open' : 'closed',
                \sprintf('%d / %d', $this->tickets->sumPaid($conference)['count'], $conference->ticketCapacity),
            ];
        }

        $io->table(['Slug', 'Name', 'Starts on', 'CFP', 'Tickets'], $rows);

        return Command::SUCCESS;
    }
}
