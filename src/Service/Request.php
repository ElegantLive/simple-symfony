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
     * Request constructor.
     */
    public function __construct ()
    {
        $this->request = RequestBase::createFromGlobals();
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
            // Never null. A JSON request with an empty (or undecodable) body used to
            // hand null to the controllers' validators, and Validator\Base::check()
            // takes an array - so the TypeError surfaced as a 500 instead of the
            // "field X is missing" 400 that the very same request produces when it
            // carries no Content-Type at all.
            return is_array($this->payload) ? $this->payload : [];
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