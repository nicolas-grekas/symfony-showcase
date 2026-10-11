<?php

namespace App\Workflow;

use App\Entity\Proposal;
use App\Message\ProposalDecided;
use App\Message\ProposalSubmitted;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;

final class ProposalWorkflowListener
{
    use ClockAwareTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[AsGuardListener(workflow: 'proposal', transition: 'submit')]
    public function guardSubmit(GuardEvent $event): void
    {
        /** @var Proposal $proposal */
        $proposal = $event->getSubject();

        if (!$proposal->conference->isCfpOpen($this->now())) {
            $event->setBlocked(true, 'The call for papers is closed.');
        }
    }

    #[AsCompletedListener(workflow: 'proposal', transition: 'submit')]
    public function onSubmitted(CompletedEvent $event): void
    {
        /** @var Proposal $proposal */
        $proposal = $event->getSubject();
        $proposal->markSubmitted($this->now());
        $this->bus->dispatch(new ProposalSubmitted($proposal->id));
    }

    #[AsCompletedListener(workflow: 'proposal', transition: 'accept')]
    #[AsCompletedListener(workflow: 'proposal', transition: 'reject')]
    public function onDecided(CompletedEvent $event): void
    {
        /** @var Proposal $proposal */
        $proposal = $event->getSubject();
        $this->bus->dispatch(new ProposalDecided($proposal->id, 'accept' === $event->getTransition()->getName()));
    }
}
