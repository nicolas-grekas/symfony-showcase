<?php

namespace App\Admin;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
final class ScheduleData
{
    /**
     * Each entry is named after its proposal, so a submitted form maps to the right talks whatever their order.
     *
     * @var array<string, SlotData>
     */
    #[FormField(CollectionType::class, ['entry_type' => SlotData::class, 'entry_name' => 'proposalId', 'entry_options' => ['label' => false]])]
    #[Assert\Valid]
    #[Assert\Unique(message: 'Two talks are in the same room at the same time.', normalizer: [SlotData::class, 'place'])]
    public array $slots = [];
}
