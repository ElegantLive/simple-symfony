<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/22
 * Time: 22:33
 */

namespace App\Message;


class SignUpNotification
{
    /**
     * @var int
     */
    private $uid;

    /**
     * SignUp constructor.
     * @param int $uid
     */
    public function __construct (int $uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return int
     */
    public function getUid (): int
    {
        return $this->uid;
    }
}