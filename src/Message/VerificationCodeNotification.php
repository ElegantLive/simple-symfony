<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/26
 * Time: 19:54
 */

namespace App\Message;


class VerificationCodeNotification
{
    /**
     * @var array
     */
    private $accessArray = ['type', 'uid', 'code', 'from', 'time'];

    /**
     * @var string
     */
    private $type;
    /**
     * @var int
     */
    private $uid;
    /**
     * @var int
     */
    private $code;
    /**
     * @var string
     */
    private $from;
    /**
     * @var int
     */
    private $time;

    /**
     * VerificationCodeNotification constructor.
     * @param array $notification
     */
    public function __construct (array $notification)
    {
        array_map(function($item) use ($notification) {
            $this->$item = $notification[$item];
        }, $this->accessArray);
    }

    /**
     * @return string
     */
    public function getType (): string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getCode (): int
    {
        return $this->code;
    }

    /**
     * @return int
     */
    public function getUid (): int
    {
        return $this->uid;
    }

    /**
     * @return string
     */
    public function getFrom (): string
    {
        return $this->from;
    }

    /**
     * @return int
     */
    public function getTime (): int
    {
        return $this->time;
    }
}