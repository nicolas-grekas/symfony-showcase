<?php

namespace App\Assistant;

use App\Entity\Conference;
use App\Entity\Proposal;
use App\Entity\Speaker;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Proposal::class)]
final class ProposalDetails
{
    public Uuid $id;

    #[Map(transform: [self::class, 'name'])]
    public string $conference;

    public string $title;
    public string $abstract;
    public string $level;
    public string $format;
    public string $status;

    /** @var list<array{name: string, company: ?string, bio: string}> */
    #[Map(transform: [self::class, 'speakers'])]
    public array $speakers;

    public static function name(Conference $conference): string
    {
        return $conference->name;
    }

    /**
     * @param Collection<int, Speaker> $speakers
     */
    public static function speakers(Collection $speakers): array
    {
        return $speakers->map(static fn (Speaker $speaker) => ['name' => $speaker->name, 'company' => $speaker->company, 'bio' => $speaker->bio])->getValues();
    }
}
