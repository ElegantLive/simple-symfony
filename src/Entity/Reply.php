<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReplyRepository")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 * @ORM\HasLifecycleCallbacks()
 */
class Reply extends Base
{
    use Timestamps;
    use SoftDeleteableEntity;

    protected $normal = ['id', 'content', 'likeCount', 'disLikeCount', 'createdAt'];

    public static $_byState = [
        self::LIKE,
        self::TIME
    ];

    const TIME = "createdAt";
    const LIKE = "likeCount";

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @ORM\Column(type="integer")
     */
    private $likeCount = 0;

    /**
     * @ORM\Column(type="integer")
     */
    private $disLikeCount = 0;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Comment")
     * @ORM\JoinColumn(nullable=false)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $reply;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getLikeCount(): ?int
    {
        return $this->likeCount;
    }

    public function setLikeCount(int $likeCount): self
    {
        $this->likeCount = $likeCount;

        return $this;
    }

    public function getDisLikeCount(): ?int
    {
        return $this->disLikeCount;
    }

    public function setDisLikeCount(int $disLikeCount): self
    {
        $this->disLikeCount = $disLikeCount;

        return $this;
    }

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    public function setComment(?Comment $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getReply(): ?int
    {
        return $this->reply;
    }

    public function setReply(?int $reply): self
    {
        $this->reply = $reply;

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
