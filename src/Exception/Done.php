<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/28
 * Time: 11:04
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Done extends Base
{
    protected $status    = Response::HTTP_CREATED;
    protected $message   = 'Already done';
    protected $errorCode = 0;
}