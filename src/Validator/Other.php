<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/20
 * Time: 14:37
 */

namespace App\Validator;

use App\Rule\Mobile;
use Symfony\Component\Validator\Constraints as Assert;

class Other extends Base
{
    public function setCollection ()
    {
        $this->collection = new Assert\Collection([
            'mobile' => new Mobile()
        ]);
    }
}