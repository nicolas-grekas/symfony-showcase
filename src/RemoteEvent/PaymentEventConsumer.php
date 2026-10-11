<?php

namespace App\RemoteEvent;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Ticket\TicketLinks;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\UuidV7;

/**
 * Consumes the webhooks of the payment provider, received at /webhook/payments.
 */
#[AsRemoteEventConsumer('payments')]
final class PaymentEventConsumer implements ConsumerInterface
{
    use ClockAwareTrait;

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly EntityManagerInterface $entityManager,
        private readonly TicketLinks $links,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function consume(RemoteEvent $event): void
    {
        $data = $event->getPayload()['data'] ?? [];
        $reference = $data['reference'] ?? '';

        // the bus runs this in a transaction: the row lock serializes the deliveries of the same payment
        if (!UuidV7::isValid($reference) || !$ticket = $this->tickets->find($reference, LockMode::PESSIMISTIC_WRITE)) {
            $this->logger->warning('Payment event "{id}" refers to no ticket.', ['id' => $event->getId()]);

            return;
        }

        match ($event->getName()) {
            'payment.succeeded' => $this->confirm($ticket, $data['payment'] ?? $event->getId()),
            'payment.failed', 'payment.canceled' => $ticket->expire(),
            default => null,
        };

        $this->entityManager->flush();
    }

    private function confirm(Ticket $ticket, string $paymentReference): void
    {
        if (!$ticket->markPaid($paymentReference, $this->now())) {
            return;
        }

        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($ticket->buyer->email, $ticket->buyer->name))
            ->subject('Your ticket for '.$ticket->conference->name)
            ->htmlTemplate('emails/ticket.html.twig')
            ->context(['ticket' => $ticket, 'url' => $this->links->url($ticket)])
        );
    }
}
