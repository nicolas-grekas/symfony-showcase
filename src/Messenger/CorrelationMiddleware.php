<?php

namespace App\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;

/**
 * Gives each message the CorrelationStamp of the message being handled, or a new one, and passes its id to the handlers after the message.
 */
final class CorrelationMiddleware implements MiddlewareInterface
{
    private ?CorrelationStamp $handled = null;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $correlation = $envelope->last(CorrelationStamp::class)) {
            $envelope = $envelope->with($correlation = $this->handled ?? new CorrelationStamp(bin2hex(random_bytes(16))));
        }

        $previous = $this->handled;
        $this->handled = $correlation;

        try {
            return $stack->next()->handle($envelope->with(new HandlerArgumentsStamp([$correlation->id])), $stack);
        } finally {
            $this->handled = $previous;
        }
    }
}
