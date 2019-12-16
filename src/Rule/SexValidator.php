<?php

namespace App\Rule;

use App\Entity\User;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SexValidator extends ConstraintValidator
{
    public function validate ($value, Constraint $constraint)
    {
        /* @var $constraint \App\Rule\Sex */

        if (null === $value || '' === $value) {
            return;
        }

        if (empty(array_key_exists($value, User::$sexScope))) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
