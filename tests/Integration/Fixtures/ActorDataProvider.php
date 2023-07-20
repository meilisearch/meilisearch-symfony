<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Fixtures;

use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\Identifier\IdNormalizerInterface;
use Meilisearch\Bundle\Tests\Entity\Actor;

/**
 * @implements DataProviderInterface<Actor>
 */
final class ActorDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly IdNormalizerInterface $idNormalizer,
    ) {
    }

    public function provide(int $limit, int $offset): array
    {
        return \array_slice([
            new Actor(1, 'Jack Nicholson'),
            new Actor(2, 'Marlon Brando'),
            new Actor(3, 'Robert De Niro'),
            new Actor(4, 'Al Pacino'),
            new Actor(5, 'Daniel Day-Lewis'),
            new Actor(6, 'Dustin Hoffman'),
            new Actor(7, 'Tom Hanks'),
            new Actor(8, 'Anthony Hopkins'),
            new Actor(9, 'Paul Newman'),
            new Actor(10, 'Denzel Washington'),
            new Actor(11, 'Spencer Tracy'),
            new Actor(12, 'Laurence Olivier'),
            new Actor(13, 'Jack Lemmon'),
            new Actor(14, 'Michael Caine'),
            new Actor(15, 'James Stewart'),
            new Actor(16, 'Robin Williams'),
            new Actor(17, 'Robert Duvall'),
            new Actor(18, 'Sean Penn'),
            new Actor(19, 'Morgan Freeman'),
            new Actor(20, 'Jeff Bridges'),
            new Actor(21, 'Sidney Poitier'),
            new Actor(22, 'Peter O\'Toole'),
            new Actor(23, 'Clint Eastwood'),
            new Actor(24, 'Gene Hackman'),
            new Actor(25, 'Charles Chaplin'),
        ], $offset, $limit);
    }

    public function loadByIdentifiers(array $identifiers): array
    {
        $actors = [];

        foreach ($this->provide(PHP_INT_MAX, 0) as $actor) {
            if ($actor->id === $identifiers['id']) {
                $actors[] = $actor;
            }
        }

        return $actors;
    }

    public function getIdentifierValues(object $object): array
    {
        \assert($object instanceof Actor);

        return ['id' => $object->id];
    }

    public function normalizeIdentifiers(array $identifiers): string|int
    {
        return $this->idNormalizer->normalize($identifiers);
    }

    public function denormalizeIdentifier(string $identifier): array
    {
        return $this->idNormalizer->denormalize($identifier);
    }

    public function cleanup(): void
    {
        // noop
    }
}
