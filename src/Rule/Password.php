<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Password extends Constraint
{
    public $message = '请输入8-16位，含字母、数字的密码';
//    public $groups = ["resetPwd", "register", "login"];
}
