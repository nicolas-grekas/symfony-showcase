<?php

namespace App\Scheduler;

use App\Enum\ProposalStatus;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Puts the proposals of the calls for papers that closed in the hands of the reviewers.
 */
#[AsCronTask('*/15 * * * *')]
final class StartReviews
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ProposalRepository $proposals,
        #[Target('proposal')]
        private readonly WorkflowInterface $workflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->proposals->findBy(['status' => ProposalStatus::Submitted]) as $proposal) {
            if ($proposal->conference->cfpClosesAt <= $this->now() && $this->workflow->can($proposal, 'start_review')) {
                $this->workflow->apply($proposal, 'start_review');
            }
        }

        $this->entityManager->flush();
    }
}
