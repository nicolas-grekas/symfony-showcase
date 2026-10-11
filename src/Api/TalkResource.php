<?php

namespace App\Api;

use App\Entity\Proposal;
use App\Entity\Speaker;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[JsonStreamable]
#[Map(source: Proposal::class)]
final class TalkResource
{
    public Uuid $id;
    public string $title;
    public string $abstract;

    #[Map(transform: [self::class, 'enumValue'])]
    public string $level;

    #[Map(transform: [self::class, 'enumValue'])]
    public string $format;

    /** @var list<string> */
    #[Map(transform: [self::class, 'speakerNames'])]
    public array $speakers;

    public static function enumValue(\BackedEnum $case): int|string
    {
        return $case->value;
    }

    /**
     * @param iterable<Speaker> $speakers
     *
     * @return list<string>
     */
    public static function speakerNames(iterable $speakers): array
    {
        $names = [];
        foreach ($speakers as $speaker) {
            $names[] = $speaker->name;
        }

        return $names;
    }
}
