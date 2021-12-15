<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\DataProvider;

interface DataProviderInterface
{
    public function provide(): array;

    public function getIndex(): string;

    public function support(string $type): bool;
}
