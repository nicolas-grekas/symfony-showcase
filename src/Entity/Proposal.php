<?php

namespace App\Entity;

use App\Entity\Material\Material;
use App\Enum\Format;
use App\Enum\Level;
use App\Enum\ProposalStatus;
use App\Enum\Room;
use App\Repository\ProposalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProposalRepository::class)]
// an HNSW index, created by a migration
#[ORM\Index(name: 'proposal_embedding_idx', columns: ['embedding'])]
class Proposal
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    #[ORM\Column(enumType: ProposalStatus::class)]
    private ProposalStatus $status = ProposalStatus::Draft;

    #[ORM\Column(nullable: true)]
    public private(set) ?DatePoint $submittedAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?DatePoint $scheduledAt = null;

    #[ORM\Column(enumType: Room::class, nullable: true)]
    public private(set) ?Room $room = null;

    /** The meaning of the title and the abstract, for the similarity search of the review assistant */
    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title', 'abstract'])]
    public private(set) ?VectorInterface $embedding = null;

    /** @var Collection<int, Speaker> */
    #[ORM\ManyToMany(targetEntity: Speaker::class)]
    public private(set) Collection $speakers;

    /** @var Collection<int, Material> */
    #[ORM\OneToMany(targetEntity: Material::class, mappedBy: 'proposal', cascade: ['persist'], orphanRemoval: true)]
    public private(set) Collection $materials;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Conference $conference,

        #[ORM\Column(length: 150)]
        public string $title,

        #[ORM\Column(type: 'text')]
        public string $abstract,

        #[ORM\Column(enumType: Level::class)]
        public Level $level,

        #[ORM\Column(enumType: Format::class)]
        public Format $format,
    ) {
        $this->id = Uuid::v7();
        $this->speakers = new ArrayCollection();
        $this->materials = new ArrayCollection();
    }

    public function getStatus(): ProposalStatus
    {
        return $this->status;
    }

    /**
     * @internal for the marking store of the proposal workflow
     */
    public function setStatus(ProposalStatus $status, array $context = []): void
    {
        $this->status = $status;
    }

    public function addSpeaker(Speaker $speaker): void
    {
        if (!$this->speakers->contains($speaker)) {
            $this->speakers->add($speaker);
        }
    }

    public function addMaterial(Material $material): void
    {
        $material->proposal = $this;
        $this->materials->add($material);
    }

    public function schedule(?DatePoint $at, ?Room $room): void
    {
        $this->scheduledAt = $at;
        $this->room = $room;
    }

    public function markSubmitted(DatePoint $at): void
    {
        $this->submittedAt = $at;
    }
}
