<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class IDs extends Constraint
{
    /*
     * Any public properties become valid options for the annotation.
     * Then, use these in your validator class.
     */
    public $message = '请输入正确的id字符串';
}
