<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
#[ORM\Entity]
class Car
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private string $name,
        #[ORM\Id]
        #[ORM\Column]
        private int $year,
    ) {
    }

    public function getModelName(): string
    {
        return $this->name;
    }

    public function getYearOfProduction(): int
    {
        return $this->year;
    }
}
