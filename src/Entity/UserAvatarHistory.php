<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserAvatarHistoryRepository")
 * @Gedmo\Uploadable(filenameGenerator="SHA1", allowOverwrite=true, appendNumber=true)
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class UserAvatarHistory extends Base
{
    use SoftDeleteableEntity;

    protected $hidden = ['user', 'deleted', 'deletedAt'];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Gedmo\UploadableFilePath
     */
    private $path;

    /**
     * @ORM\Column(type="string", length=255)
     * @Gedmo\UploadableFileName
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     * @Gedmo\UploadableFileMimeType
     */
    private $type;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=0)
     * @Gedmo\UploadableFileSize
     */
    private $size;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $publicPath;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $current = false;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="integer")
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="integer")
     */
    private $updatedAt;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="userAvatarHistories")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    public function getId (): ?int
    {
        return $this->id;
    }

    public function getPath (): ?string
    {
        return $this->path;
    }

    public function setPath (string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getName (): ?string
    {
        return $this->name;
    }

    public function setName (string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType (): ?string
    {
        return $this->type;
    }

    public function setType (string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSize (): ?string
    {
        return $this->size;
    }

    public function setSize (string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getPublicPath (): ?string
    {
        return '/uploads/' . $this->name;
    }

    public function getCurrent (): ?bool
    {
        return $this->current;
    }

    public function setCurrent (bool $current): self
    {
        $this->current = $current;

        return $this;
    }

    /**
     * Sets createdAt.
     * @param $createdAt
     * @return $this
     */
    public function setCreatedAt ($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Returns createdAt.
     * @return mixed
     */
    public function getCreatedAt ()
    {
        return $this->createdAt;
    }

    /**
     * Sets updatedAt.
     * @param $updatedAt
     * @return $this
     */
    public function setUpdatedAt ($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Returns updatedAt.
     * @return mixed
     */
    public function getUpdatedAt ()
    {
        return $this->updatedAt;
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
