<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/30
 * Time: 16:56
 */

namespace App\Validator;

use App\Entity\Reply;
use App\Rule\Enum;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Base as BaseEntity;

class ReplyPager extends Base
{
    protected function setFields ()
    {
        $this->fields = [
            "page" => new Assert\Required([
                new Assert\Type('integer'),
                new Assert\NotBlank()
            ]),
            "size" => new Assert\Required([
                new Assert\Type('integer'),
                new Assert\NotBlank()
            ]),
            "order" => new Assert\Required([
                new Assert\NotBlank(),
                new Enum([
                    'enum' => BaseEntity::$_orderState,
                    'message' => '请选择倒序正序'
                ])
            ]),
            "by" => new Assert\Required([
                new Assert\NotBlank(),
                new Enum([
                    'enum' => Reply::$_byState,
                    'message' => "请选择排序"
                ])
            ]),
        ];
    }
}