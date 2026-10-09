<?php

namespace App\Repository;

use App\Entity\Conference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\DatePoint;

/**
 * @extends ServiceEntityRepository<Conference>
 */
class ConferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conference::class);
    }

    /**
     * @return list<Conference>
     */
    public function findUpcoming(DatePoint $now): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.endsOn >= :today')
            ->setParameter('today', $now, 'day_point')
            ->orderBy('c.startsOn')
            ->getQuery()
            ->getResult();
    }
}
