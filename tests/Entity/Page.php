<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Meilisearch\Bundle\Tests\Entity\ObjectId\DummyObjectId;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'pages')]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'dummy_object_id')]
    private DummyObjectId $id;

    #[ORM\Column(type: Types::STRING)]
    #[Groups('searchable')]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups('searchable')]
    private string $content;

    public function __construct(DummyObjectId $id, string $title = 'Test page', string $content = 'Test content page')
    {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
    }

    public function getId(): DummyObjectId
    {
        return $this->id;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }
}
