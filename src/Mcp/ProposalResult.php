<?php

namespace App\Mcp;

use App\Entity\Proposal;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Proposal::class)]
final class ProposalResult
{
    public Uuid $id;

    #[Map(transform: [TalkResult::class, 'slug'])]
    public string $conference;

    public string $title;
    public string $status;

    #[Map(transform: [self::class, 'atom'])]
    public ?string $submittedAt;

    public static function atom(?DatePoint $date): ?string
    {
        return $date?->format(\DATE_ATOM);
    }
}
