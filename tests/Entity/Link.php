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
 */
#[ORM\Entity]
class Link implements NormalizableInterface
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
     * @ORM\Column(type="string")
     */
    #[ORM\Column(type: Types::STRING)]
    private string $url;

    /**
     * @ORM\Column(type="boolean", options={"default"=false})
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isSponsored;

    public function __construct(int $id, string $name = 'Test link', string $url = 'https://docs.meilisearch.com', bool $isSponsored = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
        $this->isSponsored = $isSponsored;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isSponsored(): bool
    {
        return $this->isSponsored;
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
