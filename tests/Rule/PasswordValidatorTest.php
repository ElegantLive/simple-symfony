<?php

namespace App\Tests\Rule;

use App\Rule\Password;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * The policy App\Rule\Password advertises: 8-16 characters, containing at least
 * one letter and at least one digit.
 *
 * These are the cases that matter because the validator did none of this before:
 * it inspected only the last character of the password.
 */
final class PasswordValidatorTest extends TestCase
{
    /**
     * @dataProvider acceptedPasswords
     */
    public function testAccepted(string $password, string $why): void
    {
        $this->assertCount(0, $this->violations($password), $why);
    }

    /**
     * @dataProvider rejectedPasswords
     */
    public function testRejected(string $password, string $why): void
    {
        $this->assertCount(1, $this->violations($password), $why);
    }

    public function acceptedPasswords(): array
    {
        return [
            'exactly 8 characters'  => ['Passw0rd', 'the lower bound is inclusive'],
            'exactly 16 characters' => ['Passw0rd12345678', 'the upper bound is inclusive'],
            'mixed with symbols'    => ['Str0ng@Pass', 'symbols are allowed'],
            'ends with a symbol'    => ['Passw0rd!', 'this used to be REJECTED - see the regression test'],
            'letters then digits'   => ['abcdefg1', 'order does not matter'],
        ];
    }

    public function rejectedPasswords(): array
    {
        return [
            'single character'      => ['a', 'no length check used to exist, so this passed'],
            'letters only'          => ['abcdefgh', 'needs a digit'],
            'digits only'           => ['12345678', 'needs a letter'],
            'one short of the min'  => ['Passw0r', '7 characters'],
            'one over the max'      => ['Passw0rd123456789', '17 characters'],
            'the string "0"'        => ['0', 'empty() used to swallow this and skip validation'],
        ];
    }

    /**
     * Regression guard for the pattern bug. The old code wrote its patterns as
     * '^[a-z]$^', which PHP reads with '^' as the DELIMITER and '[a-z]$' as the
     * pattern - so only the last character was ever inspected. That both let "a"
     * through and rejected any good password ending in a symbol.
     */
    public function testTheOldLastCharacterBehaviourIsGone(): void
    {
        $this->assertCount(1, $this->violations('a'), '"a" used to satisfy the policy');
        $this->assertCount(0, $this->violations('Passw0rd!'), 'this used to be refused');
    }

    /**
     * An empty value is deliberately not this validator's business: it returns
     * early so that presence is left to Assert\Required, and so that the string
     * "0" is still validated rather than treated as empty.
     */
    public function testEmptyIsLeftToTheRequiredConstraint(): void
    {
        $this->assertCount(0, $this->violations(''));
        $this->assertCount(0, $this->violations(null));
    }

    /**
     * @param string|null $password
     */
    private function violations($password): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($password, new Password());
    }
}
