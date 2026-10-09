<?php

namespace App\Ticket;

use App\Entity\Ticket;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tickets are shown to whoever has their link: it is signed and expires once the conference is over.
 */
final class TicketLinks
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function url(Ticket $ticket): string
    {
        $url = $this->urlGenerator->generate('ticket_show', ['id' => $ticket->id], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url, $ticket->conference->endsOn->modify('+1 day'));
    }
}
