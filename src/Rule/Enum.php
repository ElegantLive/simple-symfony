<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Enum extends Constraint
{
    /*
     * Any public properties become valid options for the annotation.
     * Then, use these in your validator class.
     */
    public $message = 'The value "{{ value }}" is not valid.';
    public $enum = [];
    public $emptyEnumMessage = 'please defined $enum!';
}
