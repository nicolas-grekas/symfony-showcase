<?php

namespace App\Workflow;

use App\Entity\Proposal;
use App\Enum\ProposalStatus;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\WorkflowTrait;

#[AsWorkflow(supports: Proposal::class, markingProperty: 'status', places: ProposalStatus::class)]
final class ProposalWorkflow
{
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
}
