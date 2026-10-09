<?php

namespace App\Api;

use App\Entity\Conference;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;

/**
 * The properties without #[Groups] are in every group: the serializer is configured with "enable_default_groups".
 */
#[Map(source: Conference::class)]
final class ConferenceResource
{
    public Uuid $id;
    public string $name;
    public string $slug;
    public string $city;

    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public \DateTimeImmutable $startsOn;

    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public \DateTimeImmutable $endsOn;

    #[Groups('detail')]
    public \DateTimeImmutable $cfpClosesAt;

    /** @var list<TalkResource> */
    #[Groups('detail')]
    public array $talks = [];
}
