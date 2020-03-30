<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CommentRepository")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 * @ORM\HasLifecycleCallbacks()
 */
class Comment extends Base
{
    use Timestamps;
    use SoftDeleteableEntity;

    const TIME = 'createdAt';
    const LIKE = 'likeCount';

    public static $_byState = [
        self::LIKE,
        self::TIME
    ];

    protected $hidden = ['article'];

    protected $normal = ['id', 'content', 'likeCount', 'disLikeCount', 'replyCount', 'createdAt', 'updatedAt'];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Article", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $article;

    /**
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    private $likeCount = 0;

    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    private $disLikeCount = 0;

    /**
     * @ORM\Column(type="integer")
     */
    private $replyCount = 0;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    public function getId (): ?int
    {
        return $this->id;
    }

    public function getArticle (): ?Article
    {
        return $this->article;
    }

    public function setArticle (Article $Article): self
    {
        $this->article = $Article;

        return $this;
    }

    public function getContent (): ?string
    {
        return $this->content;
    }

    public function setContent (string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getLikeCount (): ?int
    {
        return $this->likeCount;
    }

    public function setLikeCount (int $likeCount): self
    {
        $this->likeCount = $likeCount;

        return $this;
    }

    public function getDisLikeCount (): ?int
    {
        return $this->disLikeCount;
    }

    public function setDisLikeCount (int $disLikeCount): self
    {
        $this->disLikeCount = $disLikeCount;

        return $this;
    }

    public function getReplyCount(): ?int
    {
        return $this->replyCount;
    }

    public function setReplyCount(int $replyCount): self
    {
        $this->replyCount = $replyCount;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
