<?php

namespace App\Tests\Validator;

use App\Exception\Parameter;
use App\Validator\Register;
use App\Validator\RegisterCode;
use App\Validator\UserToken;
use PHPUnit\Framework\TestCase;

/**
 * The two request validators that gate authentication.
 *
 * App\Validator\Base builds a plain Symfony Collection validator, so these run
 * without a kernel. check() reports failures by throwing App\Exception\Parameter.
 */
final class AuthValidatorsTest extends TestCase
{
    private const GOOD_PASSWORD = 'Passw0rd123';

    public function testRegisterAcceptsAWellFormedPayload(): void
    {
        $this->assertAccepted(Register::class, $this->registerPayload());
    }

    public function testRegisterRejectsAWeakPassword(): void
    {
        $message = $this->assertRejected(Register::class, $this->registerPayload(['password' => 'a']));

        $this->assertStringContainsString('密码', $message);
    }

    public function testRegisterRequiresEveryField(): void
    {
        foreach (['name', 'mobile', 'email', 'sex', 'password', 'code'] as $field) {
            $payload = $this->registerPayload();
            unset($payload[$field]);

            $message = $this->assertRejected(Register::class, $payload);

            $this->assertStringContainsString($field, $message, "missing '$field' should be reported by name");
        }
    }

    public function testRegisterRequiresAWellFormedEmailCode(): void
    {
        foreach (['', '12345', '1234567', 'abcdef', '12345a'] as $bad) {
            $message = $this->assertRejected(Register::class, $this->registerPayload(['code' => $bad]));

            $this->assertStringContainsString('验证码', $message, "'$bad' should be refused as a code");
        }
    }

    public function testTheRegisterCodeRequestOnlyNeedsAnEmail(): void
    {
        $this->assertAccepted(RegisterCode::class, ['email' => 'somebody@example.com']);
        $this->assertRejected(RegisterCode::class, []);
        $this->assertRejected(RegisterCode::class, ['email' => '']);
        $this->assertRejected(RegisterCode::class, ['email' => 'not-an-email']);
    }

    public function testRegisterRejectsABadMobileAndEmail(): void
    {
        $this->assertRejected(Register::class, $this->registerPayload(['mobile' => '12345']));
        $this->assertRejected(Register::class, $this->registerPayload(['email' => 'not-an-email']));
        $this->assertRejected(Register::class, $this->registerPayload(['sex' => 'OTHER']));
        $this->assertRejected(Register::class, $this->registerPayload(['name' => 'short']));
    }

    public function testLoginAcceptsAPasswordThatSatisfiesThePolicy(): void
    {
        $this->assertAccepted(UserToken::class, [
            'mobile'   => '13900000001',
            'password' => self::GOOD_PASSWORD,
        ]);
    }

    public function testLoginRequiresMobileAndPassword(): void
    {
        $this->assertRejected(UserToken::class, ['mobile' => '13900000001']);
        $this->assertRejected(UserToken::class, ['password' => self::GOOD_PASSWORD]);
    }

    /**
     * Documenting a deliberate decision rather than asserting a preference:
     * App\Validator\UserToken applies App\Rule\Password at login as well as at
     * registration. The trade-off is that an account whose password predates the
     * policy - shorter than 8 characters, longer than 16, or missing a letter or a
     * digit - is refused here before the hash is ever compared. The comment in
     * that validator records the way out if it ever bites.
     */
    public function testLoginAppliesThePasswordPolicy(): void
    {
        $this->assertRejected(UserToken::class, [
            'mobile'   => '13900000001',
            'password' => 'a',
        ]);
    }

    private function registerPayload(array $overrides = []): array
    {
        return $overrides + [
            'name'     => 'somebody',
            'mobile'   => '13900000001',
            'email'    => 'somebody@example.com',
            'sex'      => 'MAN',
            'password' => self::GOOD_PASSWORD,
            'code'     => '123456',
        ];
    }

    private function assertAccepted(string $validatorClass, array $payload): void
    {
        try {
            (new $validatorClass())->check($payload);
        } catch (Parameter $e) {
            $this->fail("$validatorClass rejected a payload it should accept: " . $e->getMessage());
        }

        $this->addToAssertionCount(1);
    }

    private function assertRejected(string $validatorClass, array $payload): string
    {
        try {
            (new $validatorClass())->check($payload);
        } catch (Parameter $e) {
            // Counted here rather than relying on the caller to assert on the
            // message, so a rejection on its own is still a real assertion.
            $this->addToAssertionCount(1);

            return $e->getMessage();
        }

        $this->fail("$validatorClass accepted a payload it should reject");
    }
}
