<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/19
 * Time: 10:32
 */

namespace App\Exception;


class Success extends Base
{
    protected $status = 200;
    protected $message = 'OK';
    protected $errorCode = 0;
}