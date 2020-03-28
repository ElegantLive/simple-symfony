<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/28
 * Time: 16:22
 */

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;

class Comment extends Base
{
    protected function setFields ()
    {
        $this->fields = [
            'content' => new Assert\Required([
                new Assert\NotBlank([
                    'message' => '内容不能为空'
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => '内容格式错误'
                ])
            ]),
        ];
    }
}