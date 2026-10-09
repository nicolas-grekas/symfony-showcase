<?php

namespace App\Api;

use App\Entity\Proposal;
use App\Entity\Speaker;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

/**
 * Enums are mapped to their values by the object mapper.
 */
#[JsonStreamable]
#[Map(source: Proposal::class)]
final class TalkResource
{
    public Uuid $id;
    public string $title;
    public string $abstract;
    public string $level;
    public string $format;

    /** @var list<string> */
    #[Map(transform: [self::class, 'speakerNames'])]
    public array $speakers;

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
