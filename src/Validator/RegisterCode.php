<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of GET /user/register/code - the address to mail a registration
 * code to.
 *
 * Separate from Register because that one validates the whole body of the
 * registration itself and sets allowExtraFields = false; sharing it would mean
 * carrying five fields' worth of rules into a request that has one.
 */
class RegisterCode extends Base
{
    protected function setFields (): void
    {
        $this->fields = [
            'email' => new Assert\Required([
                new Assert\NotBlank([
                    'message' => '请输入邮箱'
                ]),
                new Assert\Email([
                    'message' => '请输入正确的邮箱地址'
                ])
            ]),
        ];
    }
}
