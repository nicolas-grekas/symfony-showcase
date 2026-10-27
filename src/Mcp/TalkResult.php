<?php

namespace App\Mcp;

use App\Api\TalkResource;
use App\Entity\Conference;
use App\Entity\Proposal;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Proposal::class)]
final class TalkResult
{
    public Uuid $id;

    #[Map(transform: [self::class, 'slug'])]
    public string $conference;

    public string $title;
    public string $abstract;
    public string $level;
    public string $format;

    /** @var list<string> */
    #[Map(transform: [TalkResource::class, 'speakerNames'])]
    public array $speakers;

    public static function slug(Conference $conference): string
    {
        return $conference->slug;
    }
}
