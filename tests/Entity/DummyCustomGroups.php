<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 */
#[ORM\Entity]
class DummyCustomGroups
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer")
     *
     * @Groups("public")
     */
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups('public')]
    private int $id;

    /**
     * @ORM\Column(type="string")
     *
     * @Groups("public")
     */
    #[ORM\Column(type: Types::STRING)]
    #[Groups('public')]
    private string $name;

    /**
     * @ORM\Column(type="datetime_immutable")
     *
     * @Groups("private")
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups('private')]
    private \DateTimeImmutable $createdAt;

    public function __construct(int $id, string $name, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->name = $name;
        $this->createdAt = $createdAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
