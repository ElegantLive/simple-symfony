<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/26
 * Time: 21:58
 */

namespace App\Validator;


use App\Rule\Password;
use App\Rule\Same;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePassword extends Base
{
    public $allowExtraFields = true;

    protected function setFields ()
    {
        $this->fields = [
            'password' => new Assert\Required([
                new Password()
            ]),
            'confirmPassword' => new Assert\Required([
                new Same([
                    'field' => 'password',
                    'message' => '两次输入密码不正确'
                ]),
            ])
        ];
    }
}