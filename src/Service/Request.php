<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/25
 * Time: 11:34
 */

namespace App\Service;

use Symfony\Component\HttpFoundation\Request as RequestBase;

/**
 * Class Request
 * @package App\Service
 */
class Request
{
    /**
     * @var
     */
    protected $payload;
    /**
     * @var RequestBase
     */
    public $request;
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * Request constructor.
     */
    public function __construct ()
    {
        $this->request    = RequestBase::createFromGlobals();
        self::initPayload();
    }

    /**
     * @return RequestBase
     */
    public function getRequest ()
    {
        return $this->request;
    }

    /**
     * @return mixed
     */
    public function getPayload ()
    {
        return $this->payload;
    }

    /**
     * @return array
     */
    public function getData ()
    {
        if (false !== strpos($this->getRequest()->getContentType(), 'json')) {
            return $this->payload;
        } else {
            return $this->getRequest()->request->all();
        }
    }

    /**
     *
     */
    public function initPayload (): void
    {
        if (false !== strpos($this->getRequest()->getContentType(), 'json')) {
            $this->payload = json_decode($this->getRequest()->getContent(), true);
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call ($name, $arguments)
    {
        return call_user_func_array([$this->getRequest(), $name], $arguments);
    }
}