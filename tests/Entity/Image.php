<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Test\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Image
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string")
     */
    private string $url;

    public function __construct()
    {
        $this->url = 'https://docs.meilisearch.com/logo.png';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Image
    {
        $this->id = $id;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): Image
    {
        $this->url = $url;

        return $this;
    }

    public function isPublic(): bool
    {
        return true;
    }
}
