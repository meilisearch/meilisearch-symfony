<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="posts")
 */
#[ORM\Entity]
#[ORM\Table(name: 'posts')]
class Post
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column(type="integer")
     *
     * @Groups({"searchable"})
     * ^ Note that Groups work on private properties
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups('searchable')]
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     *
     * @Groups({"searchable"})
     * ^ Note that Groups work on private properties
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups('searchable')]
    private ?string $title = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     *
     * @Groups({"searchable"})
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups('searchable')]
    private ?string $content = null;

    /**
     * @ORM\Column(type="datetime")
     *
     * @Groups({"searchable"})
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups('searchable')]
    private ?\DateTime $publishedAt = null;

    /**
     * @var Collection<int, Comment>
     *
     * @ORM\OneToMany(
     *      targetEntity="Comment",
     *      mappedBy="post",
     *      orphanRemoval=true
     * )
     *
     * @ORM\OrderBy({"publishedAt": "DESC"})
     *
     * @Groups({"searchable"})
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post', orphanRemoval: true)]
    #[ORM\OrderBy(['publishedAt' => 'DESC'])]
    #[Groups('searchable')]
    private $comments;

    /**
     * Post constructor.
     */
    public function __construct(array $attributes = [])
    {
        $this->id = $attributes['id'] ?? null;
        $this->title = $attributes['title'] ?? null;
        $this->content = $attributes['content'] ?? null;
        $this->publishedAt = $attributes['publishedAt'] ?? new \DateTime();
        $this->comments = new ArrayCollection($attributes['comments'] ?? []);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Post
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @Groups({"searchable"})
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): Post
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): Post
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @Groups({"searchable"})
     */
    public function getPublishedAt(): ?\DateTime
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTime $publishedAt): Post
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getComments(): ?Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): Post
    {
        $comment->setPost($this);
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }

        return $this;
    }

    public function removeComment(Comment $comment): Post
    {
        $comment->setPost($this);
        $this->comments->removeElement($comment);

        return $this;
    }
}
