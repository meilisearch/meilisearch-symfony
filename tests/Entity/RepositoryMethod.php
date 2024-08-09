<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Meilisearch\Bundle\Tests\Repository\RepositoryMethodRepository;

/**
 * @ORM\Entity(repositoryClass="Meilisearch\Bundle\Tests\Repository\RepositoryMethodRepository")
 *
 * @ORM\Table(name="repository_method")
 */
#[ORM\Entity(repositoryClass: RepositoryMethodRepository::class)]
#[ORM\Table(name: 'repository_method')]
class RepositoryMethod
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /**
     * @ORM\Column(type="string")
     */
    #[ORM\Column(type: Types::STRING)]
    private string $name = 'Test entity';

    /**
     * @ORM\Column(type="boolean", options={"default"=false})
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isFiltered = false;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isFiltered(): bool
    {
        return $this->isFiltered;
    }

    public function setIsFiltered(bool $isFiltered): void
    {
        $this->isFiltered = $isFiltered;
    }
}
