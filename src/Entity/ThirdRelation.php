<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ThirdRelationRepository")
 */
class ThirdRelation
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
    private $relate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $first;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $second;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRelate(): ?string
    {
        return $this->relate;
    }

    public function setRelate(string $relate): self
    {
        $this->relate = $relate;

        return $this;
    }

    public function getFirst(): ?string
    {
        return $this->first;
    }

    public function setFirst(string $first): self
    {
        $this->first = $first;

        return $this;
    }

    public function getSecond(): ?string
    {
        return $this->second;
    }

    public function setSecond(string $second): self
    {
        $this->second = $second;

        return $this;
    }
}
