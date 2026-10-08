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
     * The recipient travels with the message rather than being looked up from a
     * uid by the handler. The handler used to resolve the user from the uid and
     * return silently when there was none, which meant the register flow - where
     * no account exists yet - would report success and send nothing.
     *
     * @var array
     */
    private $accessArray = ['type', 'email', 'name', 'code', 'from', 'time'];

    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $email;
    /**
     * @var string
     */
    private $name;
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
     * @return string
     */
    public function getEmail (): string
    {
        return $this->email;
    }

    /**
     * May be empty: on the register path the account does not exist yet, so there
     * is no name to greet by.
     *
     * @return string
     */
    public function getName (): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getCode (): int
    {
        return $this->code;
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
