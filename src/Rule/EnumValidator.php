<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EnumValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        /* @var $constraint \App\Rule\Enum */

        if (null === $value || '' === $value) {
            return;
        }

        if (empty($constraint->enum)) {
            $this->context->buildViolation($constraint->emptyEnumMessage)
                ->addViolation();
            return;
        }

        if (in_array($value, $constraint->enum) === false) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
