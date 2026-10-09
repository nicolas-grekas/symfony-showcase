<?php

namespace App\Entity\Material;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Video extends Material
{
    public function __construct(
        string $url,
        #[ORM\Column(nullable: true)]
        public private(set) ?int $minutes = null,
    ) {
        parent::__construct($url);
    }
}
