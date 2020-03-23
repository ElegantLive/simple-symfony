<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/24
 * Time: 10:35
 */

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;


class SetAvatar extends Base
{
    protected function setFields (): void
    {
        $this->fields = [
            'avatar' => new Assert\Required([
                new Assert\NotBlank(),
                new Assert\Image([
                    'sizeNotDetectedMessage' => '暂时不支持该图像',
                    'disallowEmptyMessage'   => '图像损坏',
                    'maxSize'                => '5M',
                    'maxSizeMessage'         => '请上传小于5M的图像',
                ])
            ]),
        ];
    }
}