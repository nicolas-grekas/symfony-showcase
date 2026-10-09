<?php

namespace App\Repository;

use App\Entity\Proposal;
use App\Enum\ProposalStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Proposal>
 */
class ProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proposal::class);
    }

    /**
     * @return list<Proposal>
     */
    public function findAcceptedForConference(string $slug): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.conference', 'c')
            ->where('c.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', ProposalStatus::Accepted)
            ->orderBy('p.title')
            ->getQuery()
            ->getResult();
    }
}
