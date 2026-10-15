<?php

namespace App\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;

/**
 * Gives each message a MessageIdStamp, and the CorrelationStamp of the message being handled or a new one, and passes the correlation id to the handlers after the message.
 *
 * A message that its handler dispatches again, as ForwardFromOutbox does, keeps its id.
 */
final class CorrelationMiddleware implements MiddlewareInterface
{
    private ?Envelope $handled = null;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(MessageIdStamp::class)) {
            $id = $envelope->getMessage() === $this->handled?->getMessage() ? $this->handled->last(MessageIdStamp::class) : null;
            $envelope = $envelope->with($id ?? new MessageIdStamp(bin2hex(random_bytes(16))));
        }

        if (null === $correlation = $envelope->last(CorrelationStamp::class)) {
            $envelope = $envelope->with($correlation = $this->handled?->last(CorrelationStamp::class) ?? new CorrelationStamp(bin2hex(random_bytes(16))));
        }

        $previous = $this->handled;
        $this->handled = $envelope;

        try {
            return $stack->next()->handle($envelope->with(new HandlerArgumentsStamp([$correlation->id])), $stack);
        } finally {
            $this->handled = $previous;
        }
    }
}
