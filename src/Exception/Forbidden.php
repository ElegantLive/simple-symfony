<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/24
 * Time: 09:58
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Forbidden extends Base
{
    protected $status    = Response::HTTP_FORBIDDEN;
    protected $message   = '你无权访问';
    protected $errorCode = 20001;
}