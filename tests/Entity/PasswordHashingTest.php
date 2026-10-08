<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Guard rail for the switch away from md5 + a rand() salt.
 *
 * The hashing itself lives in App\Entity\Traits\Password and is reached through
 * User::setPassword() / User::verifyPassword(). Nothing here needs a database or
 * a kernel, so the suite runs with no services at all.
 *
 * @see App\Entity\Traits\Password
 */
final class PasswordHashingTest extends TestCase
{
    private const PLAIN = 'Passw0rd123';

    public function testStoredValueIsNotThePlaintext(): void
    {
        $user = new User();
        $user->setPassword(self::PLAIN);

        $stored = $this->storedHash($user);

        $this->assertNotSame(self::PLAIN, $stored);
        $this->assertStringNotContainsString(self::PLAIN, $stored);
    }

    public function testStoredValueIsAPasswordHashAndFitsTheColumn(): void
    {
        $user = new User();
        $user->setPassword(self::PLAIN);

        $stored = $this->storedHash($user);
        $info   = password_get_info($stored);

        // password_get_info() reports algoName 'unknown' and a falsy algo for a
        // bare md5 digest - which is exactly what this used to store.
        $this->assertNotSame('unknown', $info['algoName'], 'stored value is not a password_hash() output');
        $this->assertGreaterThan(0, (int) $info['algo']);
        $this->assertSame(60, strlen($stored), 'bcrypt output is 60 characters');
    }

    public function testCorrectPasswordVerifies(): void
    {
        $user = new User();
        $user->setPassword(self::PLAIN);

        $this->assertTrue($user->verifyPassword(self::PLAIN));
    }

    public function testWrongPasswordDoesNotVerify(): void
    {
        $user = new User();
        $user->setPassword(self::PLAIN);

        $this->assertFalse($user->verifyPassword('Passw0rd124'));
        $this->assertFalse($user->verifyPassword(''));
        $this->assertFalse($user->verifyPassword('PASSW0RD123'));
    }

    /**
     * The point of password_hash(): every hash carries its own random salt, so the
     * same password never yields the same digest. The old scheme drew one from
     * rand(10000000, 99999999) and stored it beside the hash.
     */
    public function testTheSamePasswordProducesDifferentHashes(): void
    {
        $a = new User();
        $a->setPassword(self::PLAIN);

        $b = new User();
        $b->setPassword(self::PLAIN);

        $this->assertNotSame(
            $this->storedHash($a),
            $this->storedHash($b),
            'two hashes of the same password must differ - the salt is not random'
        );

        $this->assertTrue($a->verifyPassword(self::PLAIN));
        $this->assertTrue($b->verifyPassword(self::PLAIN));
    }

    public function testVerifyingAgainstNoStoredHashFails(): void
    {
        $user = new User();

        $this->assertFalse($user->verifyPassword(self::PLAIN));
    }

    /**
     * Regression guard: if the old scheme is ever reintroduced, a value in that
     * format must not be accepted as a valid hash.
     */
    public function testTheOldMd5SchemeNoLongerVerifies(): void
    {
        $legacy = md5(self::PLAIN . 'doSomethingElse' . '12345678');

        $user = new User();
        $this->setStoredHash($user, $legacy);

        $this->assertFalse($user->verifyPassword(self::PLAIN));
    }

    /**
     * The separate salt column and its accessors are gone. Their absence is the
     * fix - setRand() had to run before setPassword() or the salt was an empty
     * string, and nothing enforced that order.
     */
    public function testTheCustomSaltIsGone(): void
    {
        $this->assertFalse(property_exists(User::class, 'rand'));
        $this->assertFalse(method_exists(User::class, 'setRand'));
        $this->assertFalse(method_exists(User::class, 'getRand'));
        $this->assertFalse(method_exists(User::class, 'encodePassword'));
        $this->assertFalse(method_exists(User::class, 'encodeSecret'));
    }

    private function storedHash(User $user): string
    {
        return (string) $this->passwordProperty()->getValue($user);
    }

    private function setStoredHash(User $user, string $hash): void
    {
        $this->passwordProperty()->setValue($user, $hash);
    }

    /**
     * The stored hash is not readable through the public API (deliberately - the
     * password is in $hidden), so these tests reach the property directly.
     */
    private function passwordProperty(): \ReflectionProperty
    {
        $property = new \ReflectionProperty(User::class, 'password');
        $property->setAccessible(true);

        return $property;
    }
}
