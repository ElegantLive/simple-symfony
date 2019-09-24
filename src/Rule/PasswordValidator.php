<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PasswordValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        /* @var $constraint \App\Rule\Password */
        if (empty($value)) return;

        $r1 = '^[a-z]$^';
        $r2 = '^[A-Z]$^';
        $r3 = '^[0-9]$^';
        $res = (preg_match($r1,$value) || preg_match($r2,$value) || preg_match($r3,$value));

        if (empty($res)) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
