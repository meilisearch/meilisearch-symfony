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
 * @ORM\Table(name="tags")
 */
class Tag implements NormalizableInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $id;

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private string $name;

    private int $count;

    private $public;

    private \DateTime $publishedAt;

    /**
     * Tag constructor.
     */
    public function __construct(array $attributes = [])
    {
        $this->id = $attributes['id'] ?? null;
        $this->name = $attributes['name'] ?? 'This is a tag';
        $this->count = $attributes['count'] ?? 0;
        $this->public = $attributes['public'] ?? true;
        $this->publishedAt = $attributes['publishedAt'] ?? new \DateTime();
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
