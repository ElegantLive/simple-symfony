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
     * @param Serializer $serializer
     */
    public function __construct (Serializer $serializer)
    {
        $this->request = RequestBase::createFromGlobals();
        $this->serializer = $serializer;
        self::initPayload();
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
        if (false !== strpos($this->request->getContentType(), 'json')) {
            return $this->payload;
        } else {
            return $this->request->request->all();
        }
    }

    /**
     *
     */
    public function initPayload (): void
    {
        if (false !== strpos($this->request->getContentType(), 'json')) {
            $this->payload = $this->serializer->toArray($this->request->getContent(), 'json');
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call ($name, $arguments)
    {
        return call_user_func_array([$this->request, $name], $arguments);
    }
}