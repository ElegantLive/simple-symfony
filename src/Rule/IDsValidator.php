<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IDsValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        /* @var $constraint \App\Rule\IDs */

        if (null === $value || '' === $value) {
            return;
        }

        $values = explode(',', $value);
        if (empty($values)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
            return;
        }

        $pass = true;
        foreach ($values as $id) {
            if (is_numeric($id) && is_int($id + 0) && ($id + 0) > 0) continue;
            $pass = false;
            break;
        }

        if (empty($pass)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
