<?php

namespace App\Messenger;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Records the id of each message received from the events transport, and skips the handlers of a message recorded already.
 *
 * It runs after the doctrine_transaction middleware, so that the record commits with the changes of the handlers, or rolls back with them for a retry.
 * A worker that gets a message while another one handles it waits until the transaction of the other one ends.
 */
final class InboxMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ('events' !== $consumer = $envelope->last(ReceivedStamp::class)?->getTransportName()) {
            return $stack->next()->handle($envelope, $stack);
        }

        if (!$this->connection->executeStatement('INSERT INTO messenger_inbox (consumer, message_id, handled_at) VALUES (?, ?, NOW()) ON CONFLICT DO NOTHING', [$consumer, $envelope->last(MessageIdStamp::class)->id])) {
            return $envelope;
        }

        // now and then, forget the messages recorded more than a week ago: a redelivery comes much sooner
        if (1 === random_int(1, 1000)) {
            $this->connection->executeStatement("DELETE FROM messenger_inbox WHERE consumer = ? AND handled_at < NOW() - INTERVAL '7 days'", [$consumer]);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
