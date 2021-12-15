<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\DataProvider;

interface DataProviderRegistryInterface extends \Countable, \IteratorAggregate
{
    public function filter(\Closure $func): self;
}
