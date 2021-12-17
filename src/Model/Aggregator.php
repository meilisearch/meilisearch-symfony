<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Model;

if (\PHP_VERSION_ID >= 80000) {
    class_alias('MeiliSearch\Bundle\Model\AggregatorV8', 'MeiliSearch\Bundle\Model\Aggregator');
} else {
    class_alias('MeiliSearch\Bundle\Model\AggregatorV7', 'MeiliSearch\Bundle\Model\Aggregator');
}

if (false) {
    abstract class Aggregator
    {
    }
}
