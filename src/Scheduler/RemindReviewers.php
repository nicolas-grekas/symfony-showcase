<?php

namespace App\Scheduler;

use App\Enum\ProposalStatus;
use App\Repository\ProposalRepository;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Tells the reviewers how many proposals wait for them, on Monday mornings in production only.
 */
#[When('prod')]
#[AsCronTask('0 9 * * 1')]
final class RemindReviewers
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly UserRepository $users,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(): void
    {
        if (!$count = $this->proposals->count(['status' => ProposalStatus::InReview])) {
            return;
        }

        foreach ($this->users->findBy(['enabled' => true]) as $user) {
            if (\in_array('ROLE_REVIEWER', $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true)) {
                $this->notifier->send(new Notification(\sprintf('%d proposals wait for your review', $count), ['email']), new Recipient($user->email));
            }
        }
    }
}
