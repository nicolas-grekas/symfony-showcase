<?php

namespace App;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The default schedule, whose tasks are declared with #[AsCronTask] and #[AsPeriodicTask] in src/Scheduler.
 */
#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        // missed runs are caught up after a downtime, once only, by a single worker
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->lock($this->lockFactory->createLock('scheduler_default'))
            ->processOnlyLastMissedRun(true);
    }
}
