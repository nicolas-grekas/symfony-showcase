<?php

namespace App\MessageHandler;

use App\Message\AlertOrganizers;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\AdminRecipientsProviderInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

#[AsMessageHandler]
final class AlertOrganizersHandler
{
    public function __construct(
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(AlertOrganizers $alert): void
    {
        $this->notifier->send(
            (new Notification($alert->subject, ['email']))->content($alert->details)->importance(Notification::IMPORTANCE_HIGH),
            // the admin recipients come from notifier.admin_recipients, which nothing but the notifier itself exposes
            ...($this->notifier instanceof AdminRecipientsProviderInterface ? $this->notifier->getAdminRecipients() : []),
        );
    }
}
