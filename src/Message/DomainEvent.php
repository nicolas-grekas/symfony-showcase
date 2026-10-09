<?php

namespace App\Message;

/**
 * Something that happened, published to RabbitMQ once the transaction that recorded it commits.
 */
interface DomainEvent
{
}
