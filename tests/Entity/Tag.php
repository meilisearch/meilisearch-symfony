<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Entity;

if (\PHP_VERSION_ID >= 80000) {
    class_alias('MeiliSearch\Bundle\Test\Entity\TagV8', 'MeiliSearch\Bundle\Test\Entity\Tag');
} else {
    class_alias('MeiliSearch\Bundle\Test\Entity\TagV7', 'MeiliSearch\Bundle\Test\Entity\Tag');
}

if (false) {
    class Tag
    {
    }
}
