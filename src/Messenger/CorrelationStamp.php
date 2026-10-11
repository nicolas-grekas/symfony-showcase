<?php

namespace App\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Identifies a flow of messages: the messages dispatched while handling one share its correlation id.
 */
final readonly class CorrelationStamp implements StampInterface
{
    public function __construct(
        public string $id,
    ) {
    }
}
