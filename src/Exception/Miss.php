<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/19
 * Time: 12:34
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Miss extends Base
{
    protected $status    = Response::HTTP_NOT_FOUND;
    protected $errorCode = 10002;
    protected $message   = 'missing';
}