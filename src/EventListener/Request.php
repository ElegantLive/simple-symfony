<?php


namespace App\EventListener;


use App\Service\ParameterCheck;
use App\Service\Signature;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class Request
{
    /**
     * @var ParameterCheck
     */
    private $parameterCheck;
    /**
     * @var Signature
     */
    private $signature;
    /**
     * @var string
     */
    private $env;

    /**
     * Request constructor.
     *
     * @param ParameterCheck $parameterCheck
     * @param Signature      $signature
     * @param string         $env
     */
    public function __construct(ParameterCheck $parameterCheck, Signature $signature, string $env)
    {
        $this->parameterCheck = $parameterCheck;
        $this->signature      = $signature;
        $this->env            = $env;
    }

    /**
     * @param RequestEvent $requestEvent
     * @throws InvalidArgumentException
     */
    public function onKernelRequest(RequestEvent $requestEvent)
    {
        if (!$requestEvent->isMasterRequest()) {
            // don't do anything if it's not the master request
            return;
        }

        if ($this->env === 'dev') return;

        $char = $this->signature->checkSign();

        $this->parameterCheck->checkParams($char);
    }
}