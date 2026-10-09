<?php

namespace App\Scheduler;

use App\Repository\TicketRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Gives the seats held for payments that never completed back to the sale.
 */
#[AsPeriodicTask('5 minutes')]
final class ReleaseExpiredTicketHolds
{
    use ClockAwareTrait;

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        if ($released = $this->tickets->releaseExpiredHolds($this->now())) {
            $this->logger->info('Released {count} seats held for unpaid tickets.', ['count' => $released]);
        }
    }
}
