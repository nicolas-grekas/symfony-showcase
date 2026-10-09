<?php

namespace App\Repository;

use App\Entity\Conference;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Uid\UuidV7;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * Counts the paid tickets and the seats held for pending payments.
     */
    public function countTaken(Conference $conference, DatePoint $now): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.conference = :conference')
            ->andWhere('t.status = :paid OR (t.status = :pending AND t.heldUntil > :now)')
            ->setParameter('conference', $conference)
            ->setParameter('paid', TicketStatus::Paid)
            ->setParameter('pending', TicketStatus::Pending)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Finds the ticket the user paid for or holds a seat with.
     */
    public function findCurrent(Conference $conference, User $buyer, DatePoint $now): ?Ticket
    {
        foreach ($this->findBy(['conference' => $conference, 'buyer' => $buyer, 'status' => [TicketStatus::Paid, TicketStatus::Pending]], ['id' => 'DESC']) as $ticket) {
            if (TicketStatus::Paid === $ticket->status || $ticket->isHeld($now)) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * @return array{count: int, revenue: int}
     */
    public function sumPaid(Conference $conference): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS count, COALESCE(SUM(t.price), 0) AS revenue')
            ->where('t.conference = :conference')
            ->andWhere('t.status = :paid')
            ->setParameter('conference', $conference)
            ->setParameter('paid', TicketStatus::Paid)
            ->getQuery()
            ->getSingleResult();

        return ['count' => (int) $result['count'], 'revenue' => (int) $result['revenue']];
    }

    /**
     * @return list<Ticket>
     */
    public function findPaid(Conference $conference, ?DatePoint $since = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.conference = :conference')
            ->andWhere('t.status = :paid')
            ->setParameter('conference', $conference)
            ->setParameter('paid', TicketStatus::Paid)
            ->orderBy('t.id');

        if ($since) {
            // UUIDv7 sort by time: the lowest id of a millisecond bounds the tickets ordered since then
            [$lowest] = UuidV7::createBoundaries($since);
            $qb->andWhere('t.id >= :since')->setParameter('since', $lowest, UuidType::NAME);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Ticket>
     */
    public function findPaidByBuyer(User $buyer): array
    {
        return $this->findBy(['buyer' => $buyer, 'status' => TicketStatus::Paid], ['id' => 'DESC']);
    }

    /**
     * @return int The number of seats released
     */
    public function releaseExpiredHolds(DatePoint $now): int
    {
        return $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':expired')
            ->where('t.status = :pending')
            ->andWhere('t.heldUntil <= :now')
            ->setParameter('expired', TicketStatus::Expired)
            ->setParameter('pending', TicketStatus::Pending)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
