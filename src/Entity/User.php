<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class User
{
    use Timestamps;
    use Password;

    public static $sexScope = [
        'MAN'   => '♂',
        'WOMEN' => '♀'
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
    private $name;

    /**
     * @ORM\Column(type="string", length=11)
     */
    private $mobile;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $avatar;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $rand;

    /**
     * @ORM\Column(type="string", columnDefinition="enum('MAN', 'WOMEN')")
     */
    private $sex = 'MAN';

    public function getId (): ?int
    {
        return $this->id;
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

    public function getMobile (): ?string
    {
        return $this->mobile;
    }

    public function setMobile (string $mobile): self
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getEmail (): ?string
    {
        return $this->email;
    }

    public function setEmail (string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getAvatar (): ?string
    {
        return $this->avatar;
    }

    public function setAvatar (?string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getPassword (): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return User
     */
    public function setPassword (string $password): self
    {
        $this->password = $this->encodePassword($password);

        return $this;
    }

    public function getRand (): ?string
    {
        return $this->rand;
    }

    /**
     * @return User
     */
    public function setRand (): self
    {
        $this->rand = rand(10000000, 99999999);

        return $this;
    }

    public function getSex (): ?string
    {
        return self::$sexScope[$this->sex];
    }

    public function setSex (string $sex): self
    {
        $this->sex = $sex;

        return $this;
    }

    public function encodePassword (string $password = '', string $rand = '')
    {
        $password = empty($password) ? self::getPassword() : $password;
        $rand     = empty($rand) ? self::getRand() : $rand;
        return $this->encodeSecret($password, $rand);
    }
}
