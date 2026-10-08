<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/20
 * Time: 10:47
 */

namespace App\Validator;


use App\Rule\Mobile;
use App\Rule\Password;
use Symfony\Component\Validator\Constraints as Assert;

class UserToken extends Base
{
    protected function setFields ()
    {
        $this->fields = [
            'mobile'   => new Assert\Required([
                new Mobile()
            ]),
            // The login payload is held to the same App\Rule\Password policy as
            // registration, so an input that could never have been registered is
            // rejected before the hash comparison in App\Service\UserToken runs.
            //
            // Be aware of the trade-off this carries: the policy was not actually
            // enforced before, so an account whose password is shorter than 8
            // characters, longer than 16, or missing a letter or a digit will be
            // refused here until the password is reset. Clearing user rows, or
            // adding an exception for the login path, is the way out if that ever
            // bites in an environment that has real accounts.
            'password' => new Assert\Required([
                new Password()
            ])
        ];
    }
}
