<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 16:07
 */
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait Timestamps
{
    /**
     * @ORM\Column(type="integer")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="integer")
     */
    private $updatedAt;

    /**
     * @ORM\PrePersist()
     */
    public function createdAt ()
    {
        $this->createdAt = time();
        $this->updatedAt = time();
    }

    /**
     * @ORM\PreUpdate()
     */
    public function updatedAt ()
    {
        $this->updatedAt = time();
    }
}