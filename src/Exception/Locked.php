<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/26
 * Time: 21:50
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Locked extends Base
{
    protected $status    = Response::HTTP_LOCKED;
    protected $errorCode = 10006;
    protected $message   = 'locked';
}