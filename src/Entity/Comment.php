<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CommentRepository")
 */
class Comment
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Articel", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $articel;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="text")
     */
    private $content;

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

    public function getArticel(): ?Articel
    {
        return $this->articel;
    }

    public function setArticel(Articel $articel): self
    {
        $this->articel = $articel;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
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
}
