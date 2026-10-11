<?php

namespace App\Webhook;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Server\RequestConfiguratorInterface;

/**
 * Signs the webhooks sent with Messenger according to Standard Webhooks.
 */
#[AsDecorator('webhook.signer')]
final class StandardWebhooksSigner implements RequestConfiguratorInterface
{
    public function configure(RemoteEvent $event, #[\SensitiveParameter] string $secret, HttpOptions $options): void
    {
        ['headers' => $headers, 'body' => $body] = $options->toArray();
        $headers['Webhook-Signature'] = StandardWebhooksSignature::sign($event->getId(), $headers['Webhook-Timestamp'], $body, $secret);
        $options->setHeaders($headers);
    }
}
