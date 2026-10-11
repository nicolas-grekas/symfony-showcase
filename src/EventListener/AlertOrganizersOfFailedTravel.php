<?php

namespace App\EventListener;

use App\Message\AlertOrganizers;
use App\Message\TravelCommand;
use App\Repository\ProposalRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Alerts the organizers when a travel command fails for good, instead of sending it to the failure transport.
 */
#[AsEventListener]
final class AlertOrganizersOfFailedTravel
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $command = $event->getEnvelope()->getMessage();

        if ($event->willRetry() || !$command instanceof TravelCommand) {
            return;
        }

        try {
            $this->bus->dispatch(new AlertOrganizers(
                'The travel of a speaker could not be booked',
                \sprintf('Book it by hand for "%s".', $this->proposals->find($command->proposalId)?->title),
            ));
        } catch (\Throwable $e) {
            // the failure transport keeps the command when the organizers cannot be alerted
            $this->logger->error('Failed alerting the organizers after message {class} failed.', ['class' => $command::class, 'exception' => $e]);

            return;
        }

        // marking it as sent keeps SendFailedMessageToFailureTransportListener from sending it there
        $event->addStamps(new SentToFailureTransportStamp($event->getReceiverName()));
    }
}
