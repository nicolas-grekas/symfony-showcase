<?php

namespace App\Webhook;

use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Server\RequestConfiguratorInterface;

/**
 * Sends the id and the timestamp of the webhooks, which their Standard Webhooks signature covers.
 *
 * The event name is not sent as a header, which the signature would not cover: it travels in the "type" key of the payload.
 */
#[AsDecorator('webhook.headers_configurator')]
final class StandardWebhooksHeadersConfigurator implements RequestConfiguratorInterface
{
    use ClockAwareTrait;

    public function configure(RemoteEvent $event, #[\SensitiveParameter] string $secret, HttpOptions $options): void
    {
        $options->setHeaders([
            'Webhook-Id' => $event->getId(),
            'Webhook-Timestamp' => (string) $this->now()->getTimestamp(),
        ]);
    }
}
