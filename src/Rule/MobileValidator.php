<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class MobileValidator extends ConstraintValidator
{
    public function validate ($value, Constraint $constraint)
    {
        /* @var $constraint \App\Rule\Mobile */
        if (empty($value)) return;

        $rule = '/^1(3|4|5|7|8)[0-9]\d{8}$/';
        $res  = preg_match($rule, $value);

        if (empty($res)) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
