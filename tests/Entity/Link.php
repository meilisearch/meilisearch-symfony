<?php

namespace MeiliSearch\Bundle\Test\Entity;

use Doctrine\ORM\Mapping as ORM;
use MeiliSearch\Bundle\Searchable;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="links")
 */
class Link implements NormalizableInterface
{

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var mixed|string $name
     */
    private $name;

    /**
     * @var mixed|null $url
     */
    private $url;

    /**
     * Link constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->id   = $attributes['id'] ?? null;
        $this->name = $attributes['name'] ?? 'This is a tag';
        $this->url  = $attributes['url'] ?? null;
    }

    private function isSponsored(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function normalize(NormalizerInterface $normalizer, $format = null, array $context = [])
    {
        if (Searchable::NORMALIZATION_FORMAT === $format) {
            return [
                'id'   => $this->id,
                'name' => 'this test is correct',
                'url'  => 'https://www.meilisearch.com',
            ];
        }

        return [];
    }
}
