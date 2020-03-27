<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ThirdRelationRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class ThirdRelation extends Base
{
    use Timestamps;

    const ARTICLE_TAGS     = 'ARTICLE_TAGS';
    const ARTICLE_LIKES    = 'ARTICLE_LIKES';
    const ARTICLE_DISLIKES = 'ARTICLE_DISLIKES';
    const COMMENT_LIKES    = 'ARTICLE_LIKES';
    const COMMENT_DISLIKES = 'ARTICLE_DISLIKES';
    const REPLY_LIKES      = 'ARTICLE_LIKES';
    const REPLY_DISLIKES   = 'ARTICLE_DISLIKES';

    public static $types = [
        self::ARTICLE_DISLIKES,
        self::ARTICLE_LIKES,
        self::ARTICLE_TAGS,
        self::COMMENT_DISLIKES,
        self::COMMENT_LIKES,
        self::REPLY_DISLIKES,
        self::REPLY_LIKES
    ];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $relate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $first;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $second;

    public function getId (): ?int
    {
        return $this->id;
    }

    public function getRelate (): ?string
    {
        return $this->relate;
    }

    public function setRelate (string $relate): self
    {
        $this->relate = $relate;

        return $this;
    }

    public function getFirst (): ?string
    {
        return $this->first;
    }

    public function setFirst (string $first): self
    {
        $this->first = $first;

        return $this;
    }

    public function getSecond (): ?string
    {
        return $this->second;
    }

    public function setSecond (string $second): self
    {
        $this->second = $second;

        return $this;
    }
}
