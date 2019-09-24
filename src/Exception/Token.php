<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 14:36
 */

namespace App\Exception;


class Token extends Base
{
    protected $status = 400;
    protected $message = 'token无效或已过期';
    protected $errorCode = 10001;
}