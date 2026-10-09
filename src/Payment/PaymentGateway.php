<?php

namespace App\Payment;

use App\Entity\Ticket;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sends buyers to the checkout page hosted by the payment provider, which reports the outcome with a webhook.
 */
final class PaymentGateway
{
    public function __construct(
        #[Autowire(env: 'PAYMENT_CHECKOUT_URL')]
        private readonly string $checkoutUrl,
    ) {
    }

    public function checkoutUrl(Ticket $ticket, string $returnUrl): string
    {
        return $this->checkoutUrl.'?'.http_build_query([
            'reference' => (string) $ticket->id,
            'amount' => $ticket->price,
            'currency' => 'EUR',
            'description' => 'Ticket for '.$ticket->conference->name,
            'return_url' => $returnUrl,
        ]);
    }
}
