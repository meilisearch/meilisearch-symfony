<?php

namespace MeiliSearch\Bundle\Test\Entity;

use DateTime;
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
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private $name;

    private $count;

    private $public;

    private $publishedAt;

    /**
     * Tag constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->id          = $attributes['id'] ?? null;
        $this->name        = $attributes['name'] ?? 'This is a tag';
        $this->count       = $attributes['count'] ?? 0;
        $this->public      = $attributes['public'] ?? true;
        $this->publishedAt = $attributes['publishedAt'] ?? new DateTime();
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
     * @inheritDoc
     * @throws ExceptionInterface
     */
    public function normalize(NormalizerInterface $normalizer, $format = null, array $context = [])
    {
        if (Searchable::NORMALIZATION_FORMAT === $format) {
            return [
                'id'          => $this->id,
                'name'        => 'this test is correct',
                'count'       => 10,
                'publishedAt' => $normalizer->normalize($this->publishedAt),
            ];
        }

        return true;
    }
}