<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Never sends more emails than the provider accepts: over the limit, the async message is retried later.
 */
#[AsEventListener]
final class ThrottleEmails
{
    public function __construct(
        #[Target('mailer')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        if ($event->isQueued()) {
            return;
        }

        $limit = $this->limiter->create()->consume();

        if (!$limit->isAccepted()) {
            throw new RecoverableMessageHandlingException('Rate limit for mailer transport exceeded.', retryDelay: max(0, $limit->getRetryAfter()->getTimestamp() - time()) * 1000);
        }
    }
}
