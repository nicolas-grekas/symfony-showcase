<?php

namespace App\Api;

use App\Entity\Conference;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;

#[Map(source: Conference::class)]
#[Groups('detail')]
final class ConferenceResource
{
    #[Groups('list')]
    public Uuid $id;

    #[Groups('list')]
    public string $name;

    #[Groups('list')]
    public string $slug;

    #[Groups('list')]
    public string $city;

    #[Groups('list')]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public \DateTimeImmutable $startsOn;

    #[Groups('list')]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public \DateTimeImmutable $endsOn;

    public \DateTimeImmutable $cfpClosesAt;

    /** @var list<TalkResource> */
    public array $talks = [];
}
