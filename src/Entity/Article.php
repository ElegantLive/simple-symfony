<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticleRepository")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 * @ORM\HasLifecycleCallbacks()
 */
class Article extends Base
{
    use Timestamps;
    use SoftDeleteableEntity;

    protected $hidden = ['user'];
    protected $trust  = ['title', 'content', 'description'];

    const VIEW = 'view_count';
    const TIME = 'created_at';

    public static $_byState = [
        self::VIEW,
        self::TIME
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
    private $title;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $description;

    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    private $viewCount = 0;

    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    private $commentCount = 0;

    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    private $favorite = 0;

    /**
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    public function getId (): ?int
    {
        return $this->id;
    }

    public function getTitle (): ?string
    {
        return $this->title;
    }

    public function setTitle (string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription (): ?string
    {
        return $this->description;
    }

    public function setDescription (string $description): self
    {
        $this->description = $description;

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

    public function getViewCount (): ?int
    {
        return $this->viewCount;
    }

    public function setViewCount (int $viewCount): self
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function getCommentCount (): ?int
    {
        return $this->commentCount;
    }

    public function setCommentCount (int $commentCount): self
    {
        $this->commentCount = $commentCount;

        return $this;
    }

    public function getFavorite (): ?int
    {
        return $this->favorite;
    }

    public function setFavorite (int $favorite): self
    {
        $this->favorite = $favorite;

        return $this;
    }

    public function getUser (): ?User
    {
        return $this->user;
    }

    public function setUser (?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function suppleTagsToArticle (ServiceEntityRepository $repository)
    {
        $suppleTag = [
            'relate' => ThirdRelation::ARTICLE_TAGS,
            'first' => $this->getId(),
        ];

        $tagList = $this->findBy($map);

        $tagIds = [];
        foreach ($tagList as $item) {
            array_push($tagIds, $item->getSecond());
        }

        if ($tagIds) {
            $tagIds  = implode(',', $tagIds);
            $tagList = $repository->findBy($tagIds);

            array_map(function (Tag $tag) {
                $tagItem = [
                    'id'   => $tag->getId(),
                    'name' => $tag->getName()
                ];

                array_push($suppleTag, $tagItem);
            }, $tagList);
        }

        return $suppleTag;
    }
}
