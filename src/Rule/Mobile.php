<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Mobile extends Constraint
{
    public $message = 'please input mobile';
}
