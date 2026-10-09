<?php

namespace App\MessageHandler;

use App\Message\BookSpeakerTravel;
use App\Repository\ProposalRepository;
use App\Travel\TravelAgency;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class BookSpeakerTravelHandler
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly TravelAgency $travelAgency,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(BookSpeakerTravel $command): void
    {
        $proposal = $this->proposals->find($command->proposalId) ?? throw new UnrecoverableMessageHandlingException(\sprintf('Proposal "%s" not found.', $command->proposalId));

        $this->logger->info('Booked the travel of the speakers of "{title}": {reference}.', [
            'title' => $proposal->title,
            'reference' => $this->travelAgency->book($proposal),
        ]);
    }
}
