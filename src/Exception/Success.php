<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/19
 * Time: 10:32
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Success extends Base
{
    protected $status    = Response::HTTP_OK;
    protected $message   = 'OK';
    protected $errorCode = 0;
}