<?php

namespace App\Mcp;

use App\Api\TalkResource;
use App\Entity\Proposal;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Proposal::class)]
final class SlotResult
{
    #[Map(source: 'scheduledAt', transform: [ProposalResult::class, 'atom'])]
    public string $startsAt;

    public string $room;
    public string $title;

    /** @var list<string> */
    #[Map(transform: [TalkResource::class, 'speakerNames'])]
    public array $speakers;
}
