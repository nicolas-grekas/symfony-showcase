<?php

namespace App\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Identifies a message, which keeps its id when it is retried, redelivered or forwarded from the outbox.
 */
final readonly class MessageIdStamp implements StampInterface
{
    public function __construct(
        public string $id,
    ) {
    }
}
