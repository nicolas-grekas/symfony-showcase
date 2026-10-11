<?php

namespace App\Admin;

use App\Entity\Proposal;
use App\Enum\Room;
use Symfony\Component\Clock\DatePoint;

final class SlotData
{
    public ?DatePoint $startsAt = null;

    public ?Room $room = null;

    public function __construct(
        public readonly string $proposalId,
        public readonly string $title,
    ) {
    }

    public static function fromProposal(Proposal $proposal): self
    {
        $slot = new self((string) $proposal->id, $proposal->title);
        $slot->startsAt = $proposal->scheduledAt;
        $slot->room = $proposal->room;

        return $slot;
    }

    /**
     * Tells the slots apart by value, as the Unique constraint compares objects by identity; unscheduled talks share no place.
     */
    public static function place(self $slot): string
    {
        return $slot->startsAt && $slot->room ? $slot->room->value.'@'.$slot->startsAt->format('Y-m-d H:i') : 'unscheduled:'.$slot->proposalId;
    }
}
