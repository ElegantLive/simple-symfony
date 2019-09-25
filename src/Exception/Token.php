<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 14:36
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Token extends Base
{
    protected $status = Response::HTTP_UNAUTHORIZED;
    protected $message = 'token无效或已过期';
    protected $errorCode = 10001;
}