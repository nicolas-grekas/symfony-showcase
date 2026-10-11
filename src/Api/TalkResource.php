<?php

namespace App\Api;

use App\Entity\Proposal;
use App\Entity\Speaker;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[JsonStreamable]
#[Map(source: Proposal::class)]
#[Groups('detail')]
final class TalkResource
{
    #[ValueTransformer(nativeToStream: 'strval')]
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
