<?php

namespace App\MessageHandler;

use App\Message\ProposalSubmitted;
use App\Repository\ProposalRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[AsMessageHandler(fromTransport: 'events')]
final class NotifyReviewersOfNewProposal
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly UserRepository $users,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ProposalSubmitted $event): void
    {
        $proposal = $this->proposals->find($event->proposalId) ?? throw new UnrecoverableMessageHandlingException(\sprintf('Proposal "%s" not found.', $event->proposalId));

        foreach ($this->users->findAll() as $user) {
            if (!$user->enabled || !\in_array('ROLE_REVIEWER', $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true)) {
                continue;
            }

            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($user->email, $user->name))
                ->subject('New proposal: '.$proposal->title)
                ->htmlTemplate('emails/new_proposal.html.twig')
                ->context(['proposal' => $proposal])
            );
        }
    }
}
