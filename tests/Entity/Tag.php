<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Entity;

use Doctrine\ORM\Mapping as ORM;
use MeiliSearch\Bundle\Searchable;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @ORM\Entity
 */
class Tag implements NormalizableInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string")
     */
    private string $name = '';

    /**
     * @ORM\Column(type="smallint")
     */
    private int $count = 0;

    private bool $public = true;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTimeInterface $publishedAt;

    public function __construct()
    {
        $this->publishedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Tag
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Tag
    {
        $this->name = $name;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): Tag
    {
        $this->public = $public;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @throws ExceptionInterface
     */
    public function normalize(NormalizerInterface $normalizer, $format = null, array $context = [])
    {
        if (Searchable::NORMALIZATION_FORMAT === $format) {
            return [
                'id' => $this->id,
                'name' => 'this test is correct',
                'count' => 10,
                'publishedAt' => $normalizer->normalize($this->publishedAt),
            ];
        }

        return true;
    }
}
