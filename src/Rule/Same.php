<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Same extends Constraint
{
    /*
     * Any public properties become valid options for the annotation.
     * Then, use these in your validator class.
     */
    public $message = 'This value "{{ value }}" is not valid.';
    public $field;
    public $emptyFieldError = 'Please enter same field';
}
