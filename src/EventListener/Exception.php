<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/17
 * Time: 09:59
 */

namespace App\EventListener;

use App\Exception\Base;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Class Exception
 * @package App\EventListener
 */
class Exception
{
    /**
     * @var
     */
    private $env;

    /**
     * @var
     */
    private $event;

    /**
     * @var int
     */
    private $statusCode = 500;

    /**
     * @var int
     */
    private $errorCode = 999;

    /**
     * @var string
     */
    private $message = '服务器异常';

    /**
     * @var array
     */
    private $data = [];

    /**
     * ExceptionListener constructor.
     * @param $env
     */
    public function __construct ($env)
    {
        $this->env = $env;
    }

    /**
     * @return int
     */
    private function getStatusCode (): int
    {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     */
    private function setStatusCode (int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return int
     */
    private function getErrorCode (): int
    {
        return $this->errorCode;
    }

    /**
     * @param int $errorCode
     */
    private function setErrorCode (int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    /**
     * @return string
     */
    private function getMessage (): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    private function setMessage (string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return array
     */
    private function getData (): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    private function setData (array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return ExceptionEvent
     */
    private function getEvent (): ExceptionEvent
    {
        return $this->event;
    }

    /**
     * @param mixed $event
     */
    private function setEvent ($event): void
    {
        $this->event = $event;
    }

    /**
     * @param ExceptionEvent $event
     * @return ExceptionEvent
     */
    public function onKernelException (ExceptionEvent $event)
    {
        $this->setEvent($event);
        $exception = $event->getException();
        if ($exception instanceof Base) {
            $this->setData($exception->getData());
            $this->setMessage($exception->getMessage());
            $this->setErrorCode($exception->getErrorCode());
            $this->setStatusCode($exception->getStatus());

            $event->setResponse($this->createJsonResponse());
            return $event;
        }

        if ($this->env !== 'dev') $event->setResponse($this->createJsonResponse());

        return $event;
    }

    /**
     * @return JsonResponse
     */
    private function createJsonResponse ()
    {
        return JsonResponse::create([
            'message'    => $this->getMessage(),
            'errorCode'  => $this->getErrorCode(),
            'data'       => $this->getData(),
            'requestUrl' => $this->getEvent()->getRequest()->getPathInfo()
        ], $this->getStatusCode());
    }
}