<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/24
 * Time: 10:35
 */

namespace App\Validator;

use App\Rule\Mobile;
use App\Rule\Password;
use App\Rule\Sex;
use Symfony\Component\Validator\Constraints as Assert;


class Register extends Base
{
    protected function setFields (): void
    {
        $this->fields = [
            'mobile'   => new Assert\Required([
                new Mobile(),
            ]),
            'password' => new Assert\Required([
                new Password(),
            ]),
            'sex'      => new Assert\Required([
                new Sex(),
            ]),
            'name'     => new Assert\Required([
                new Assert\NotBlank(),
                new Assert\NotNull(),
                new Assert\Length([
                    "min" => 6
                ])
            ]),
            'email'    => new Assert\Required([
                new Assert\NotBlank(),
                new Assert\Email([
                    'message' => '请输入正确的邮箱地址'
                ])
            ]),
            // The code mailed by GET /user/register/code. Register sets
            // allowExtraFields = false, so an undeclared 'code' would be rejected
            // outright rather than ignored.
            'code'     => new Assert\Required([
                new Assert\NotBlank([
                    'message' => '请输入邮箱验证码'
                ]),
                new Assert\Regex([
                    'pattern' => '/^[0-9]{6}$/',
                    'message' => '邮箱验证码不正确'
                ])
            ]),
        ];
    }


}