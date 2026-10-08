<?php

namespace App\Entity;

use App\Entity\Traits\Password;
use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 * @ORM\HasLifecycleCallbacks()
 */
class User extends Base
{
    use Password;
    use Timestamps;
    use SoftDeleteableEntity;

    public static $sexScope = [
        'MAN'   => '♂',
        'WOMEN' => '♀'
    ];

    protected $trust = ['sex', 'name'];
    protected $hidden = ['password', 'deletedAt', 'deleted'];
    protected $normal = ['id', 'sex', 'name', 'createdAt', 'avatar'];

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
     * 255 to fit any password_hash() output: bcrypt is 60 characters, argon2id
     * around 97. It was 32, which was exactly an md5 hex digest.
     *
     * @ORM\Column(type="string", length=255)
     */
    private $password;

    /**
     * NOT NULL has to be written inside columnDefinition, not expressed through
     * the usual column attributes: a column definition string is emitted
     * verbatim, so every other attribute (including notnull) is ignored when the
     * DDL is generated. Without it the column is created nullable while the
     * mapping still claims not null, and doctrine:schema:validate then fails
     * forever - it compares notnull, ignores columnDefinition entirely, and the
     * ALTER it keeps emitting is generated from this same string, so it can
     * never repair the column.
     *
     * @ORM\Column(type="string", columnDefinition="enum('MAN','WOMEN') NOT NULL")
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
     * Hashes and stores the plaintext password. The hashing lives in the
     * App\Entity\Traits\Password trait so the algorithm is stated in one place.
     *
     * @param string $password
     * @return User
     */
    public function setPassword (string $password): self
    {
        $this->password = $this->hashSecret($password);

        return $this;
    }

    /**
     * Check a plaintext password against the stored hash.
     *
     * @param string $password
     * @return bool
     */
    public function verifyPassword (string $password): bool
    {
        return $this->verifySecret($password, $this->password);
    }

    public function getSex ($default = false): ?string
    {
        $type = $default ? array_flip(self::$sexScope): self::$sexScope;

        return $type[$this->sex];
    }

    public function setSex (string $sex): self
    {
        $this->sex = $sex;

        return $this;
    }
}
