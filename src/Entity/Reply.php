<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReplyRepository")
 */
class Reply
{
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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $reply;

    /**
     * @ORM\Column(type="integer")
     */
    private $likeCount;

    /**
     * @ORM\Column(type="integer")
     */
    private $disLikeCount;

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

    public function getReply(): ?int
    {
        return $this->reply;
    }

    public function setReply(?int $reply): self
    {
        $this->reply = $reply;

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
}
