<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Entity;

if (\PHP_VERSION_ID >= 80000) {
    class_alias('MeiliSearch\Bundle\Test\Entity\LinkV8', 'MeiliSearch\Bundle\Test\Entity\Link');
} else {
    class_alias('MeiliSearch\Bundle\Test\Entity\LinkV7', 'MeiliSearch\Bundle\Test\Entity\Link');
}

if (false) {
    class Link
    {
    }
}
