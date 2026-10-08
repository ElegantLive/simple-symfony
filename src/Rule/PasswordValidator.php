<?php

namespace App\Rule;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PasswordValidator extends ConstraintValidator
{
    /**
     * Enforces the policy App\Rule\Password advertises: 8-16 characters, with at
     * least one letter and at least one digit.
     *
     * This validator used to be a no-op in disguise. It wrote its patterns as
     * '^[a-z]$^', which PHP reads with '^' as the DELIMITER and '[a-z]$' as the
     * pattern - so it only ever inspected the LAST character of the password.
     * Three consequences, all real:
     *
     *   - "a" and "abcdefgh" satisfied a policy advertised as
     *     "8-16 characters, letters and digits", because both end in a letter
     *   - a perfectly good password ending in a symbol was rejected - including
     *     at login, where App\Validator\UserToken applied the same rule, so a
     *     correct password could be refused before it was ever compared
     *   - the length was never checked at all
     */
    public function validate ($value, Constraint $constraint)
    {
        /* @var $constraint \App\Rule\Password */

        // empty() would also swallow the string "0", letting it skip validation.
        if (null === $value || '' === $value) return;

        $value  = (string) $value;
        $length = mb_strlen($value, 'UTF-8');

        $valid = $length >= 8
            && $length <= 16
            && preg_match('/[a-zA-Z]/', $value) === 1
            && preg_match('/[0-9]/', $value) === 1;

        if (!$valid) {
            // Deliberately no setParameter('{{ value }}', ...) here: the message
            // has no {{ value }} placeholder, so it would only copy the plaintext
            // password into the violation object, and from there into the logs
            // and the profiler.
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
