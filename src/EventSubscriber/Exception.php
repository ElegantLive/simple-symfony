<?php

namespace App\EventSubscriber;

use App\Exception\Base;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class Exception implements EventSubscriberInterface
{
    /**
     * @var
     */
    private $env;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ExceptionListener constructor.
     * @param                 $env
     * @param LoggerInterface $logger
     */
    public function __construct ($env, LoggerInterface $logger)
    {
        $this->env    = $env;
        $this->logger = $logger;
    }

    public function onExceptionEvent (ExceptionEvent $event)
    {
        if ($this->env === 'dev') return;

        $exception = $event->getException();
        if ($exception instanceof Base) return;

        // logger error message
        $this->logger->error($exception->getMessage(), [
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'trace' => $exception->getTrace()
        ]);
    }

    public static function getSubscribedEvents ()
    {
        return [
            ExceptionEvent::class => 'onExceptionEvent',
        ];
    }
}
