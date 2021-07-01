<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table(name="comments")
 */
class Comment
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"searchable"})
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Post", inversedBy="comments")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Post $post = null;

    /**
     * @var string
     * @ORM\Column(type="text")
     *     min=5,
     *     minMessage="comment.too_short",
     *     max=10000,
     *     maxMessage="comment.too_long"
     * )
     * @Groups({"searchable"})
     */
    private $content;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime")
     * @Groups({"searchable"})
     */
    private $publishedAt;

    /**
     * Comment constructor.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->id = $attributes['id'] ?? null;
        $this->content = $attributes['content'] ?? null;
        $this->publishedAt = $attributes['publishedAt'] ?? new \DateTime();
        $this->post = $attributes['post'] ?? null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Comment
    {
        $this->id = $id;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): Comment
    {
        $this->content = $content;

        return $this;
    }

    public function getPublishedAt(): \DateTime
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTime $publishedAt): Comment
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(Post $post): Comment
    {
        $this->post = $post;

        return $this;
    }
}
