<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TagRepository")
 */
class Tag
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $description;

    /**
     * @ORM\Column(type="integer")
     */
    private $useCount;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isHot;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getUseCount(): ?int
    {
        return $this->useCount;
    }

    public function setUseCount(int $useCount): self
    {
        $this->useCount = $useCount;

        return $this;
    }

    public function getIsHot(): ?bool
    {
        return $this->isHot;
    }

    public function setIsHot(bool $isHot): self
    {
        $this->isHot = $isHot;

        return $this;
    }
}
