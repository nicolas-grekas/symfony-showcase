<?php

namespace App\MessageHandler;

use App\Message\AlertOrganizers;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;

#[AsMessageHandler]
final class AlertOrganizersHandler
{
    public function __construct(
        // NotifierInterface does not give the admin recipients of the configuration
        #[Autowire(service: 'notifier')]
        private readonly Notifier $notifier,
    ) {
    }

    public function __invoke(AlertOrganizers $alert): void
    {
        $this->notifier->send(
            (new Notification($alert->subject, ['email']))->content($alert->details)->importance(Notification::IMPORTANCE_HIGH),
            ...$this->notifier->getAdminRecipients(),
        );
    }
}
