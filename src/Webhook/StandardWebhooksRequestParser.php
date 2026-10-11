<?php

namespace App\Webhook;

use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Accepts the webhooks signed according to Standard Webhooks, named after the "type" key of their payload.
 *
 * @see https://www.standardwebhooks.com/
 */
final class StandardWebhooksRequestParser extends AbstractRequestParser
{
    use ClockAwareTrait;

    /**
     * How far, in seconds, the timestamp of a webhook may be from now before it is rejected as a replay.
     */
    private const TIMESTAMP_TOLERANCE = 300;

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $payload = $request->toArray();

        foreach (['Webhook-Id', 'Webhook-Timestamp', 'Webhook-Signature'] as $header) {
            if (!$request->headers->has($header)) {
                throw new RejectWebhookException(406, \sprintf('Missing "%s" HTTP request signature header.', $header));
            }
        }

        $id = $request->headers->get('Webhook-Id');
        $timestamp = $request->headers->get('Webhook-Timestamp');
        $expected = StandardWebhooksSignature::sign($id, $timestamp, $request->getContent(), $secret);

        // the header lists one signature per secret while the secret is rotated
        $signatures = preg_split('/\s++/', trim($request->headers->get('Webhook-Signature')), -1, \PREG_SPLIT_NO_EMPTY);

        if (!array_any($signatures, static fn (string $signature) => hash_equals($expected, $signature))) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }

        if (!preg_match('/^-?+\d++$/', $timestamp) || abs($this->now()->getTimestamp() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            throw new RejectWebhookException(406, 'The "Webhook-Timestamp" HTTP request header is outside the accepted window.');
        }

        if (!\is_string($payload['type'] ?? null)) {
            throw new RejectWebhookException(406, 'The payload is missing the "type" key holding the event name.');
        }

        return new RemoteEvent($payload['type'], $id, $payload);
    }
}
