<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/20
 * Time: 10:47
 */

namespace App\Validator;


use App\Rule\Mobile;
use Symfony\Component\Validator\Constraints as Assert;

class UserToken extends Base
{
    protected function setFields ()
    {
        $this->fields = [
            'mobile'   => new Assert\Required([
                new Mobile()
            ]),
            // The password is only checked for presence here. App\Rule\Password
            // is a *policy* rule (length, character mix) and belongs at
            // registration and password change, never at login: applying it here
            // can only ever reject a legitimate user whose existing password
            // predates the policy - which is exactly what used to happen, since
            // the rule also rejected anything ending in a symbol. Whether the
            // password is correct is decided by the hash comparison in
            // App\Service\UserToken.
            'password' => new Assert\Required([])
        ];
    }
}
