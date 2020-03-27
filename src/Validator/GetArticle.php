<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/27
 * Time: 15:07
 */

namespace App\Validator;


use App\Entity\Article;
use App\Entity\Base as BaseEntity;
use App\Rule\Enum;
use Symfony\Component\Validator\Constraints as Assert;

class GetArticle extends Base
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
                    'enum' => Article::$_byState,
                    'message' => "请选择排序"
                ])
            ]),
        ];
    }
}