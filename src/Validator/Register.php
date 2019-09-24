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
    protected function setCollection (): void
    {
        $this->collection =  new Assert\Collection([
            'mobile' => new Assert\Required([
                new Mobile(),
            ]),
            'password' => new Assert\Required([
                new Password(),
            ]),
            'sex' => new Assert\Required([
                new Sex(),
            ]),
            'name' => new Assert\Required([
                new Assert\NotBlank(),
                new Assert\NotNull(),
                new Assert\Length([
                    "min" => 6
                ])
            ]),
            'avatar' => new Assert\Required([
                new Assert\NotBlank(),
                new Assert\NotNull(),
//                new Assert\()
            ]),
            'email' => new Assert\Required([
                new Assert\NotBlank(),
                new Assert\Email()
            ]),
        ]);;
    }
}