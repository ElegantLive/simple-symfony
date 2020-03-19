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
        $time = time();
        $this->setCreatedAt($time);
        $this->setUpdatedAt($time);
    }

    /**
     * @ORM\PreUpdate()
     */
    public function updatedAt ()
    {
        $this->setUpdatedAt(time());
    }

    /**
     * @param bool $default
     * @return mixed
     */
    public function getUpdatedAt ($default = false)
    {
        $time = $this->isTimestamp($this->updatedAt);

        if ($default) {
            return $time ? $this->updatedAt: strtotime($this->updatedAt);
        } else {
            return $time ? date('Y-m-d H:i:s', $this->updatedAt): $this->updatedAt;
        }
    }

    /**
     * checkout string is valid timestamp
     * @param $string
     * @return bool
     */
    private function isTimestamp ($string)
    {
        $is = true;
        try {
            new \DateTime('@' . $string);
        } catch (\Exception $exception) {
            $is = false;
        }

        return $is;
    }

    /**
     * @param mixed $updatedAt
     */
    public function setUpdatedAt ($updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param bool $default
     * @return mixed
     */
    public function getCreatedAt ($default = false)
    {
        $time = $this->isTimestamp($this->createdAt);

        if ($default) {
            return $time ? $this->createdAt: strtotime($this->createdAt);
        } else {
            return $time ? date('Y-m-d H:i:s', $this->createdAt): $this->createdAt;
        }
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt ($createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}