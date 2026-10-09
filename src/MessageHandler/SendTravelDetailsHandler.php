<?php

namespace App\MessageHandler;

use App\Message\SendTravelDetails;
use App\Repository\ProposalRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class SendTravelDetailsHandler
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(SendTravelDetails $command): void
    {
        $proposal = $this->proposals->find($command->proposalId) ?? throw new UnrecoverableMessageHandlingException(\sprintf('Proposal "%s" not found.', $command->proposalId));

        foreach ($proposal->speakers as $speaker) {
            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($speaker->email, $speaker->name))
                ->subject('Your travel to '.$proposal->conference->city)
                ->htmlTemplate('emails/travel_details.html.twig')
                ->context(['proposal' => $proposal])
            );
        }
    }
}
