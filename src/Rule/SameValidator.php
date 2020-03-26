<?php

namespace App\Rule;

use App\Exception\Parameter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SameValidator extends ConstraintValidator
{
    public function validate ($value, Constraint $constraint)
    {
        /* @var $constraint \App\Rule\Same */

        if (null === $value || '' === $value) {
            return;
        }

        if (empty($constraint->field)) throw new Parameter(['message' => $constraint->emptyFieldError]);

        $password = $this->context->getRoot()[$constraint->field];
        if ($password !== $value) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
