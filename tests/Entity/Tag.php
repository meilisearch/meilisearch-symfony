<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Meilisearch\Bundle\SearchableObject;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @ORM\Entity
 */
#[ORM\Entity]
class Tag implements NormalizableInterface
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /**
     * @ORM\Column(type="string")
     */
    #[ORM\Column(type: Types::STRING)]
    private string $name;

    /**
     * @ORM\Column(type="smallint")
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $count = 0;

    private bool $public = true;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $publishedAt;

    public function __construct(int $id, string $name = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * @throws ExceptionInterface
     */
    public function normalize(NormalizerInterface $normalizer, $format = null, array $context = []): array
    {
        if (SearchableObject::NORMALIZATION_FORMAT === $format) {
            return [
                'id' => $this->id,
                'name' => 'this test is correct',
                'count' => 10,
                'publishedAt' => $normalizer->normalize($this->publishedAt),
            ];
        }

        return [];
    }
}
