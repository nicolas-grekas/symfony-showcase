<?php

namespace App\Command;

use App\Entity\Conference;
use App\Enum\TicketStatus;
use App\Repository\ConferenceRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Groups the commands run as "conference <command>", or "conference:<command>".
 */
#[AsCommand('conference', description: 'Manage the conferences')]
final class ConferenceCommands
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ConferenceRepository $conferences,
        private readonly TicketRepository $tickets,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[AsCommand('list', description: 'List the conferences and their ticket sales')]
    public function list(SymfonyStyle $io, #[Option(description: 'Include the past conferences')] bool $all = false): int
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

    /**
     * @param InputFile[] $files
     */
    #[AsCommand('import', description: 'Import conferences from JSON files', usages: ['conferences.json', '2027/*.json'])]
    public function import(SymfonyStyle $io, #[Argument(description: 'JSON files holding a list of conferences')] array $files): int
    {
        $imported = 0;
        foreach ($files as $file) {
            foreach (json_decode($file->getContents(), true, flags: \JSON_THROW_ON_ERROR) as $data) {
                if ($this->conferences->findOneBy(['slug' => $data['slug']])) {
                    $io->note(\sprintf('Skipping "%s", which exists already.', $data['slug']));

                    continue;
                }

                $this->entityManager->persist(new Conference(
                    $data['name'],
                    $data['slug'],
                    $data['city'],
                    new DatePoint($data['startsOn']),
                    new DatePoint($data['endsOn']),
                    new DatePoint($data['cfpOpensAt']),
                    new DatePoint($data['cfpClosesAt']),
                    $data['ticketCapacity'] ?? 0,
                    $data['ticketPrice'] ?? 0,
                ));
                ++$imported;
            }
        }

        $this->entityManager->flush();
        $io->success(\sprintf('Imported %d conference(s) from %d file(s).', $imported, \count($files)));

        return Command::SUCCESS;
    }

    /**
     * @param list<TicketStatus> $status
     */
    #[AsCommand('tickets', description: 'List the tickets of a conference')]
    public function tickets(
        SymfonyStyle $io,
        #[Argument(description: 'The slug of the conference')] string $slug,
        #[Option(description: 'Only the tickets with these statuses')] array $status = [],
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
