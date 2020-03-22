<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/22
 * Time: 23:40
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Used extends Base
{
    protected $status    = Response::HTTP_IM_USED;
    protected $message   = '资源被占用';
    protected $errorCode = 10004;
}