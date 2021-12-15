<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\DataProvider;

final class DataProviderRegistry implements DataProviderRegistryInterface
{
    /**
     * @var DataProviderInterface[]
     */
    private iterable $registries;

    /**
     * @param DataProviderInterface[] $registries
     */
    public function __construct(iterable $registries)
    {
        $this->registries = $registries;
    }

    public function filter(\Closure $func): self
    {
        return new self(\array_filter($this->registries, $func));
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->registries);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->registries);
    }
}
