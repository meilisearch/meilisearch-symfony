<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Meilisearch\Bundle\DataProvider\OrmEntityProvider;
use Meilisearch\Bundle\Identifier\DefaultIdNormalizer;
use Meilisearch\Bundle\Tests\Entity\Movie;
use Meilisearch\Bundle\Tests\Entity\ObjectId\DummyObjectId;
use Meilisearch\Bundle\Tests\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrmEntityProviderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testEveryRowIsProvidedOnceWhenRowsAreDeletedWhileWalking(): void
    {
        $movies = $this->persistMovies(9);
        $provider = $this->createProvider(Movie::class);

        $provided = $this->identifiersOf($provider->provide(3, 0));

        // Entities handed out by the first page are removed, as an import that deletes
        // what it has already processed would do.
        $this->entityManager->remove($movies[0]);
        $this->entityManager->remove($movies[1]);
        $this->entityManager->flush();

        $provided = array_merge($provided, $this->identifiersOf($provider->provide(3, 3)));
        $provided = array_merge($provided, $this->identifiersOf($provider->provide(3, 6)));

        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], $provided);
    }

    public function testPagesAreUnchangedWhenNothingIsDeleted(): void
    {
        $this->persistMovies(5);
        $provider = $this->createProvider(Movie::class);

        self::assertSame([1, 2], $this->identifiersOf($provider->provide(2, 0)));
        self::assertSame([3, 4], $this->identifiersOf($provider->provide(2, 2)));
        self::assertSame([5], $this->identifiersOf($provider->provide(2, 4)));
        self::assertSame([], $this->identifiersOf($provider->provide(2, 5)));
    }

    public function testAnOffsetThatDoesNotContinueTheWalkIsHonouredAndThenContinued(): void
    {
        $this->persistMovies(12);
        $provider = $this->createProvider(Movie::class);

        self::assertSame([1, 2, 3], $this->identifiersOf($provider->provide(3, 0)));

        // An absolute window, then the walk carries on from where that window ended.
        self::assertSame([7, 8, 9], $this->identifiersOf($provider->provide(3, 6)));
        self::assertSame([10, 11, 12], $this->identifiersOf($provider->provide(3, 9)));
    }

    public function testARepeatedOutOfRangeRequestStaysEmpty(): void
    {
        $this->persistMovies(9);
        $provider = $this->createProvider(Movie::class);

        self::assertSame([], $this->identifiersOf($provider->provide(3, 30)));
        self::assertSame([], $this->identifiersOf($provider->provide(3, 30)));
    }

    public function testAnOutOfRangeRequestAfterAWalkDoesNotResumeTheWalk(): void
    {
        $this->persistMovies(40);
        $provider = $this->createProvider(Movie::class);

        self::assertSame([1, 2, 3], $this->identifiersOf($provider->provide(3, 0)));
        self::assertSame([4, 5, 6], $this->identifiersOf($provider->provide(3, 3)));

        self::assertSame([], $this->identifiersOf($provider->provide(3, 300)));
        self::assertSame([], $this->identifiersOf($provider->provide(3, 300)));
    }

    public function testResetDropsTheStateOfAnInterruptedWalk(): void
    {
        $movies = $this->persistMovies(12);
        $provider = $this->createProvider(Movie::class);

        // A walk that stops part way, as an import interrupted by an exception or a timeout does.
        self::assertSame([1, 2, 3], $this->identifiersOf($provider->provide(3, 0)));
        self::assertSame([4, 5, 6], $this->identifiersOf($provider->provide(3, 3)));

        foreach ([$movies[0], $movies[1], $movies[2]] as $movie) {
            $this->entityManager->remove($movie);
        }
        $this->entityManager->flush();

        $provider->reset();

        // A later request asking for an absolute window must get that window, not the rows the
        // abandoned walk would have continued with.
        self::assertSame([10, 11, 12], $this->identifiersOf($provider->provide(3, 6)));
    }

    public function testAnIdentifierItCannotSeekIsPagedByOffset(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->entityManager->persist(new Page(new DummyObjectId($i), 'Page '.$i, 'Content '.$i));
        }
        $this->entityManager->flush();

        $provider = $this->createProvider(Page::class);

        self::assertCount(2, $provider->provide(2, 0));
        self::assertCount(2, $provider->provide(2, 2));
        self::assertCount(1, $provider->provide(2, 4));
    }

    /**
     * @param class-string $className
     */
    private function createProvider(string $className): OrmEntityProvider
    {
        return new OrmEntityProvider(
            self::getContainer()->get('doctrine'),
            new DefaultIdNormalizer(),
            $className,
        );
    }

    /**
     * @return list<Movie>
     */
    private function persistMovies(int $count): array
    {
        $movies = [];

        for ($i = 1; $i <= $count; ++$i) {
            $this->entityManager->persist($movies[] = new Movie('Movie '.$i, 'comedy'));
        }

        $this->entityManager->flush();

        return $movies;
    }

    /**
     * @param array<Movie> $movies
     *
     * @return list<int>
     */
    private function identifiersOf(array $movies): array
    {
        return array_map(static fn (Movie $movie): int => $movie->getId(), array_values($movies));
    }
}
