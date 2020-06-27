<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/19
 * Time: 12:34
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;

class Gone extends Base
{
    protected $status    = Response::HTTP_GONE;
    protected $errorCode = 10005;
    protected $message   = 'gone';
}