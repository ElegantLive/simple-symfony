<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/17
 * Time: 11:52
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Parameter extends Base
{
    protected $status = Response::HTTP_BAD_REQUEST;
    protected $errorCode = 10000;
    protected $message = "invalid parameters";
}