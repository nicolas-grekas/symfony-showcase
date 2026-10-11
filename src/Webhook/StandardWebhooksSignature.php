<?php

namespace App\Webhook;

/**
 * Signs webhooks according to Standard Webhooks, for the ones sent and the ones received alike.
 *
 * @see https://www.standardwebhooks.com/
 */
final class StandardWebhooksSignature
{
    public static function sign(string $id, string $timestamp, string $body, #[\SensitiveParameter] string $secret): string
    {
        // the specification serializes the secret as base64 behind a "whsec_" prefix
        if (str_starts_with($secret, 'whsec_')) {
            $secret = base64_decode(substr($secret, 6), true) ?: $secret;
        }

        return 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $secret, true));
    }
}
