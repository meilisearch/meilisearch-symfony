<?php

namespace MeiliSearch\Bundle\Test\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table(name="posts")
 */
class Post
{

    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"searchable"})
     * ^ Note that Groups work on private properties
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"searchable"})
     * ^ Note that Groups work on private properties
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime")
     */
    private $publishedAt;

    /**
     * @var Comment[]|ArrayCollection
     * @ORM\OneToMany(
     *      targetEntity="Comment",
     *      mappedBy="post",
     *      orphanRemoval=true
     * )
     * @ORM\OrderBy({"publishedAt": "DESC"})
     */
    private $comments;

    /**
     * Post constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->id          = $attributes['id'] ?? null;
        $this->title       = $attributes['title'] ?? null;
        $this->content     = $attributes['content'] ?? null;
        $this->publishedAt = $attributes['publishedAt'] ?? new DateTime();
        $this->comments    = new ArrayCollection($attributes['comments'] ?? []);
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
    public function getPublishedAt(): ?DateTime
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTime $publishedAt): Post
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getComments(): ?ArrayCollection
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
        $comment->setPost(null);
        $this->comments->removeElement($comment);

        return $this;
    }
}
