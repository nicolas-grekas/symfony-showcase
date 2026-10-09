<?php

namespace App\Scheduler;

use App\Enum\ProposalStatus;
use App\Repository\ProposalRepository;
use App\Workflow\ProposalWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Puts the proposals of the calls for papers that closed in the hands of the reviewers.
 */
#[AsCronTask('*/15 * * * *')]
final class StartReviews
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly ProposalWorkflow $workflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->proposals->findBy(['status' => ProposalStatus::Submitted]) as $proposal) {
            if ($proposal->conference->cfpClosesAt <= $this->now() && $this->workflow->can($proposal, ProposalWorkflow::START_REVIEW)) {
                $this->workflow->apply($proposal, ProposalWorkflow::START_REVIEW);
            }
        }

        $this->entityManager->flush();
    }
}
