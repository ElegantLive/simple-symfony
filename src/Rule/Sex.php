<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Sex extends Constraint
{
    public $message = '请选择性别';
}
