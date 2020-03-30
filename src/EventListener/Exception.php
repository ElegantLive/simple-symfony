<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/17
 * Time: 09:59
 */

namespace App\EventListener;

use App\Exception\Base;
use App\Exception\Miss;
use App\Message\ExceptionNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

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
     * @var LoggerInterface
     */
    private $logger;

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
     * @var string
     */
    private $routingMissMessage = 'routing miss';

    /**
     * @var int
     */
    private $routingMissErrorCode = 998;

    /**
     * @var array
     */
    private $data = [];
    /**
     * @var string
     */
    private $from;
    /**
     * @var string
     */
    private $dev;
    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * ExceptionListener constructor.
     * @param                 $env
     * @param LoggerInterface $logger
     */
    public function __construct ($env, $from, $dev, LoggerInterface $logger, MessageBusInterface $bus)
    {
        $this->env    = $env;
        $this->logger = $logger;
        $this->from   = $from;
        $this->dev    = $dev;
        $this->bus    = $bus;
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
     * @return string
     */
    public function getRoutingMissMessage (): string
    {
        return $this->routingMissMessage;
    }

    /**
     * @return int
     */
    public function getRoutingMissErrorCode (): int
    {
        return $this->routingMissErrorCode;
    }

    /**
     * @param ExceptionEvent $event
     * @return ExceptionEvent
     */
    public function onKernelException (ExceptionEvent $event)
    {
        $this->setEvent($event);
        $exception = $event->getException();

        if ($exception instanceof NotFoundHttpException ||
            $exception instanceof MethodNotAllowedHttpException) {
            $exception = new Miss([
                'message' => $this->getRoutingMissMessage(),
                'errorCode' => $this->getRoutingMissErrorCode()
            ]);
        }

        if ($exception instanceof Base) {
            $this->setData($exception->getData());
            $this->setMessage($exception->getMessage());
            $this->setErrorCode($exception->getErrorCode());
            $this->setStatusCode($exception->getStatus());

            $event->setResponse($this->createJsonResponse());
            return $event;
        }

        if ($this->env === 'dev') return $event;

        $event->setResponse($this->createJsonResponse());

        // logger error message or other ...
        $this->logger->error($event->getException()->getMessage(), [
            'file'  => $event->getException()->getFile(),
            'line'  => $event->getException()->getLine(),
            'trace' => $event->getException()->getTrace()
        ]);
        $this->bus->dispatch(new ExceptionNotification($event->getRequest(), $event->getException(), [
            'sender'   => $this->from,
            'receiver' => $this->dev
        ]));

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