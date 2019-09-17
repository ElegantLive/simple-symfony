<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/17
 * Time: 11:52
 */

namespace App\Exception;


class Parameter extends Base
{
    protected $status = 400;
    protected $errorCode = 10000;
    protected $message = "参数错误";
}