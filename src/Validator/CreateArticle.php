<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/27
 * Time: 10:45
 */

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;

class CreateArticle extends Base
{
    protected function setFields ()
    {
        $this->fields = [
            'title' => new Assert\Required([
                new Assert\NotBlank([
                    'message' => '标题不能为空'
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => '标题格式错误'
                ])
            ]),
            'content' => new Assert\Required([
                new Assert\NotBlank([
                    'message' => '内容不能为空'
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => '内容格式错误'
                ])
            ]),
            'tag' => [
                new Assert\Type(['type' => 'array']),
                new Assert\Count(['max' => 3]),
            ],
            'description' => [
                new Assert\Type([
                    'type' => 'string',
                    'message' => '内容格式错误'
                ]),
                new Assert\Length([
                    'max' => 75,
                    'maxMessage' => '简介最多25个字'
                ])
            ]
        ];
    }
}