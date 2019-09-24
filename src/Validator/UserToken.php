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
    public function setCollection ()
    {
        $this->collection = new Assert\Collection([
            'mobile' => new Mobile(),
            'password' => new Password()
        ]);
    }
}