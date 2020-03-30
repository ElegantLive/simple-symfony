<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/30
 * Time: 17:30
 */

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;

class PostReply extends Base
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