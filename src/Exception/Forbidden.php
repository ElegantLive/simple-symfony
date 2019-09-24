<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/24
 * Time: 09:58
 */

namespace App\Exception;


class Forbidden extends Base
{
    protected $status = 401;
    protected $message = '你无权访问';
    protected $errorCode = 20001;
}