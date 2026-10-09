<?php

namespace App\Enum;

use Symfony\Component\Workflow\Attribute\Place;

enum ProposalStatus: string
{
    #[Place(initial: true)]
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
