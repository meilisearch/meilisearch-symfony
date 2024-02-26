<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Meilisearch\Bundle\Searchable;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @ORM\Entity
 *
 * @ORM\InheritanceType("JOINED")
 *
 * @ORM\DiscriminatorColumn(name="type", type="integer")
 *
 * @ORM\DiscriminatorMap({1 = ExternalLink::class, 2 = InternalLink::class})
 */
#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'integer')]
#[ORM\DiscriminatorMap([1 => ExternalLink::class, 2 => InternalLink::class])]
abstract class Link implements NormalizableInterface
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
    private string $name = 'Test link';

    /**
     * @ORM\Column(type="string")
     */
    #[ORM\Column(type: Types::STRING)]
    private string $url = 'https://docs.meilisearch.com';

    /**
     * @ORM\Column(type="boolean", options={"default"=false})
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isSponsored = false;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): Link
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Link
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): Link
    {
        $this->url = $url;

        return $this;
    }

    public function isSponsored(): bool
    {
        return $this->isSponsored;
    }

    public function setIsSponsored(bool $isSponsored): Link
    {
        $this->isSponsored = $isSponsored;

        return $this;
    }

    public function normalize(NormalizerInterface $normalizer, $format = null, array $context = []): array
    {
        if (Searchable::NORMALIZATION_FORMAT === $format) {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'url' => $this->url,
            ];
        }

        return [];
    }
}
