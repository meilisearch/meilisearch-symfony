<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'comments')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups('searchable')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private Post $post;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups('searchable')]
    private string $content;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups('searchable')]
    private \DateTimeImmutable $publishedAt;

    public function __construct(Post $post, string $content, ?\DateTimeImmutable $publishedAt = null)
    {
        $this->post = $post;
        $this->content = $content;
        $this->publishedAt = $publishedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): Comment
    {
        $this->content = $content;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }
}
