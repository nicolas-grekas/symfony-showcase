<?php

namespace App\MessageHandler;

use App\Message\BookSpeakerTravel;
use App\Message\ProposalDecided;
use App\Repository\ProposalRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class HandleProposalDecision
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly MailerInterface $mailer,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ProposalDecided $event): void
    {
        $proposal = $this->proposals->find($event->proposalId) ?? throw new UnrecoverableMessageHandlingException(\sprintf('Proposal "%s" not found.', $event->proposalId));

        foreach ($proposal->speakers as $speaker) {
            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($speaker->email, $speaker->name))
                ->subject($event->accepted ? 'Your talk is accepted!' : 'About your proposal')
                ->htmlTemplate('emails/decision.html.twig')
                ->context(['proposal' => $proposal, 'accepted' => $event->accepted])
            );
        }

        if (!$event->accepted) {
            return;
        }

        $this->bus->dispatch(new BookSpeakerTravel($proposal->id));
    }
}
