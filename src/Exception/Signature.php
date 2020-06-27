<?php


namespace App\Exception;


class Signature extends Base
{
    protected $status = 401;
    protected $message = "签名已失效";
    protected $errorCode = 10007;
}