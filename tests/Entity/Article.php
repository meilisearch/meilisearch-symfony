<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Article extends ContentItem
{
}
