<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/24
 * Time: 15:19
 */

namespace App\Message;


use Symfony\Component\HttpFoundation\Request;

class ExceptionNotification
{
    /**
     * @var Request
     */
    private $request;
    /**
     * @var \Exception
     */
    private $exception;
    /**
     * @var array
     */
    private $fromTo;

    public function __construct (Request $request, \Exception $exception, array $fromTo)
    {
        $this->request   = $request;
        $this->exception = $exception;
        $this->fromTo    = $fromTo;
    }

    /**
     * @return \Exception
     */
    public function getException (): \Exception
    {
        return $this->exception;
    }

    /**
     * @return Request
     */
    public function getRequest (): Request
    {
        return $this->request;
    }

    /**
     * @return array|mixed
     */
    public function getSender ()
    {
        return array_key_exists('sender', $this->fromTo) ? $this->fromTo['sender'] : [];
    }

    /**
     * @return array|mixed
     */
    public function getReceiver ()
    {
        return array_key_exists('receiver', $this->fromTo) ? $this->fromTo['receiver'] : [];
    }
}