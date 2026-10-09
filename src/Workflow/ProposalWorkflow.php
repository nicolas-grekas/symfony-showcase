<?php

namespace App\Workflow;

use App\Entity\Proposal;
use App\Enum\ProposalStatus;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\WorkflowTrait;

#[AsWorkflow(supports: Proposal::class, markingProperty: 'status', places: ProposalStatus::class)]
final class ProposalWorkflow
{
    use ClockAwareTrait;
    use WorkflowTrait;

    #[Transition(from: ProposalStatus::Draft, to: ProposalStatus::Submitted)]
    public const SUBMIT = 'submit';

    #[Transition(from: ProposalStatus::Submitted, to: ProposalStatus::InReview)]
    public const START_REVIEW = 'start_review';

    #[Transition(from: ProposalStatus::InReview, to: ProposalStatus::Accepted)]
    public const ACCEPT = 'accept';

    #[Transition(from: ProposalStatus::InReview, to: ProposalStatus::Rejected)]
    public const REJECT = 'reject';

    #[Transition(from: ProposalStatus::Draft, to: ProposalStatus::Withdrawn)]
    #[Transition(from: ProposalStatus::Submitted, to: ProposalStatus::Withdrawn)]
    #[Transition(from: ProposalStatus::InReview, to: ProposalStatus::Withdrawn)]
    public const WITHDRAW = 'withdraw';

    #[AsGuardListener(transition: self::SUBMIT)]
    public function guardSubmit(GuardEvent $event): void
    {
        /** @var Proposal $proposal */
        $proposal = $event->getSubject();

        if (!$proposal->conference->isCfpOpen($this->now())) {
            $event->setBlocked(true, 'The call for papers is closed.');
        }
    }

    #[AsCompletedListener(transition: self::SUBMIT)]
    public function onSubmitted(CompletedEvent $event): void
    {
        /** @var Proposal $proposal */
        $proposal = $event->getSubject();
        $proposal->markSubmitted($this->now());
    }
}
