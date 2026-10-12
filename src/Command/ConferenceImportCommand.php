<?php

namespace App\Command;

use App\Entity\Conference;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand('conference:import', description: 'Import conferences from JSON files', usages: ['conferences.json', '2027/*.json'])]
final class ConferenceImportCommand
{
    public function __construct(
        private readonly ConferenceRepository $conferences,
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @param string[] $files
     */
    public function __invoke(SymfonyStyle $io, #[Argument(description: 'JSON files holding a list of conferences')] array $files): int
    {
        $imported = 0;
        foreach (array_map($this->filesystem->readFile(...), $files) as $contents) {
            foreach (json_decode($contents, true, flags: \JSON_THROW_ON_ERROR) as $data) {
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
}
