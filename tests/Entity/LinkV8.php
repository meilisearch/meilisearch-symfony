<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Entity;

use Doctrine\ORM\Mapping as ORM;
use MeiliSearch\Bundle\Searchable;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @ORM\Entity
 */
class LinkV8 implements NormalizableInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="string")
     */
    private string $name = 'Test link';

    /**
     * @ORM\Column(type="string")
     */
    private string $url = 'https://docs.meilisearch.com';

    /**
     * @ORM\Column(type="boolean", options={"default"=false})
     */
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

    /**
     * {@inheritDoc}
     */
    public function normalize(NormalizerInterface $normalizer, $format = null, array $context = []): array|string|int|float|bool
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
