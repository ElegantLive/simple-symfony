<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TaskListRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class TaskList
{
    use Timestamps;

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
     * @ORM\Column(type="integer")
     */
    private $list_id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $background;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Task", mappedBy="list")
     */
    private $tasks;

    public function __construct ()
    {
        $this->tasks = new ArrayCollection();
    }

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

    public function getListId (): ?int
    {
        return $this->list_id;
    }

    public function setListId (int $list_id): self
    {
        $this->list_id = $list_id;

        return $this;
    }

    public function getBackground (): ?string
    {
        return $this->background;
    }

    public function setBackground (string $background): self
    {
        $this->background = $background;

        return $this;
    }

    /**
     * @return Collection|Task[]
     */
    public function getTasks (): Collection
    {
        return $this->tasks;
    }

    public function addTask (Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks[] = $task;
            $task->setList($this);
        }

        return $this;
    }

    public function removeTask (Task $task): self
    {
        if ($this->tasks->contains($task)) {
            $this->tasks->removeElement($task);
            // set the owning side to null (unless already changed)
            if ($task->getList() === $this) {
                $task->setList(null);
            }
        }

        return $this;
    }
}
