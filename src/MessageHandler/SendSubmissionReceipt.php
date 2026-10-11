<?php

namespace App\MessageHandler;

use App\Message\ProposalSubmitted;
use App\Repository\ProposalRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Mime\Address;

/**
 * Runs after NotifyReviewersOfNewProposal, which has the default priority, so that a speaker never hears of a proposal the reviewers have not.
 */
#[AsMessageHandler(priority: -1)]
final class SendSubmissionReceipt
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ProposalSubmitted $event, ?CorrelationStamp $correlation = null): void
    {
        $proposal = $this->proposals->find($event->proposalId) ?? throw new UnrecoverableMessageHandlingException(\sprintf('Proposal "%s" not found.', $event->proposalId));

        foreach ($proposal->speakers as $speaker) {
            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($speaker->email, $speaker->name))
                ->subject('We received your proposal')
                ->htmlTemplate('emails/submission_receipt.html.twig')
                ->context(['proposal' => $proposal, 'reference' => $correlation?->getId()])
            );
        }
    }
}
