<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="pages")
 */
#[ORM\Entity]
#[ORM\Table(name: 'pages')]
class Page
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="NONE")
     *
     * @ORM\Column(type="object")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: Types::OBJECT)]
    private $id = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     *
     * @Groups({"searchable"})
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups('searchable')]
    private ?string $title = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     *
     * @Groups({"searchable"})
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups('searchable')]
    private ?string $content = null;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
