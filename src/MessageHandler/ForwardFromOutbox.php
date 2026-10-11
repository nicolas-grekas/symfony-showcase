<?php

namespace App\MessageHandler;

use App\Message\DomainEvent;
use App\Message\TravelCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Sends the messages of the outbox to RabbitMQ: a message reaches the outbox only once the transaction that stored it commits.
 *
 * The other handlers of these messages handle them from the "events" transport only.
 */
#[AsMessageHandler(fromTransport: 'outbox')]
final class ForwardFromOutbox
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(DomainEvent|TravelCommand $message): void
    {
        $this->bus->dispatch($message, [new TransportNamesStamp('events')]);
    }
}
