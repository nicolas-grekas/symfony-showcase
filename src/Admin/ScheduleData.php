<?php

namespace App\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final class ScheduleData
{
    /**
     * Keyed by the ids of the proposals, which name the entries, so a submitted form maps to the right talks whatever their order.
     *
     * @var array<string, SlotData>
     */
    #[Assert\Valid]
    #[Assert\Unique(message: 'Two talks are in the same room at the same time.', normalizer: [SlotData::class, 'place'])]
    public array $slots = [];
}
