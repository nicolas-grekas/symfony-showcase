<?php

namespace App\Entity;

use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Uid\UuidV7;

/**
 * The id of a ticket tells when it was ordered: sales reports query ranges of ids.
 */
#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket
{
    public const HOLD = '+15 minutes';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) UuidV7 $id;

    #[ORM\Column(enumType: TicketStatus::class)]
    public private(set) TicketStatus $status = TicketStatus::Pending;

    #[ORM\Column]
    public private(set) int $price;

    /** The seat is kept for the buyer until then, while the payment is pending. */
    #[ORM\Column(type: 'date_point')]
    public private(set) DatePoint $heldUntil;

    #[ORM\Column(nullable: true)]
    public private(set) ?string $paymentReference = null;

    #[ORM\Column(type: 'date_point', nullable: true)]
    public private(set) ?DatePoint $paidAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Conference $conference,

        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) User $buyer,

        DatePoint $orderedAt,
    ) {
        $this->id = new UuidV7(UuidV7::generate($orderedAt));
        $this->price = $conference->ticketPrice;
        $this->heldUntil = $orderedAt->modify(self::HOLD);
    }

    public function isHeld(DatePoint $now): bool
    {
        return TicketStatus::Pending === $this->status && $now < $this->heldUntil;
    }

    /**
     * @return bool Whether the ticket was not paid already: providers may report a payment more than once
     */
    public function markPaid(string $paymentReference, DatePoint $now): bool
    {
        if (TicketStatus::Paid === $this->status) {
            return false;
        }

        $this->status = TicketStatus::Paid;
        $this->paymentReference = $paymentReference;
        $this->paidAt = $now;

        return true;
    }

    public function expire(): void
    {
        if (TicketStatus::Pending === $this->status) {
            $this->status = TicketStatus::Expired;
        }
    }
}
