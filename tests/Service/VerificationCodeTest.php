<?php

namespace App\Tests\Service;

use App\Exception\Locked;
use App\Exception\Miss;
use App\Exception\Parameter;
use App\Service\VerificationCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\MessageBus;

/**
 * The attempt limiting added to App\Service\VerificationCode.
 *
 * An in-memory cache and a bus with no middleware are enough: sendCode()
 * dispatches a notification and nothing here depends on it being handled, so the
 * whole lifecycle can be stepped through without Redis, a worker or a mail round
 * trip.
 *
 * The cache keys are built by the service as sprintf('<type format>', $uid), so
 * the tests reconstruct the same strings to look inside. If that format ever
 * changes these tests must change with it.
 */
final class VerificationCodeTest extends TestCase
{
    private const UID    = 4242;
    private const FORMAT = 'change_password_%s';

    /** @var ArrayAdapter */
    private $cache;

    /** @var VerificationCode */
    private $service;

    protected function setUp(): void
    {
        $this->cache   = new ArrayAdapter();
        $this->service = new VerificationCode($this->cache, new MessageBus([]), 'from@example.com');
    }

    public function testSendCodeStoresASixDigitCode(): void
    {
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);

        $code = $this->cachedCode();
        $this->assertIsInt($code);
        $this->assertGreaterThanOrEqual(200000, $code);
        $this->assertLessThanOrEqual(999999, $code);

        $this->assertNull($this->cachedAttempts(), 'a fresh code starts with no failed attempts recorded');
    }

    public function testASecondCodeCannotBeRequestedWhileOneIsLive(): void
    {
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);

        $this->expectException(Locked::class);
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);
    }

    public function testWrongGuessesBelowTheLimitAreRejectedButKeepTheCode(): void
    {
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);
        $wrong = $this->cachedCode() + 1;

        for ($attempt = 1; $attempt < VerificationCode::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, $wrong);
                $this->fail('a wrong code was accepted');
            } catch (Parameter $e) {
                // expected
            }

            $this->assertSame($attempt, $this->cachedAttempts(), 'the attempt counter should track the failures');
            $this->assertNotNull($this->cachedCode(), 'the code stays valid until the limit is reached');
        }
    }

    public function testTheLastAllowedGuessBurnsTheCode(): void
    {
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);
        $correct = $this->cachedCode();
        $wrong   = $correct + 1;

        for ($attempt = 1; $attempt < VerificationCode::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, $wrong);
            } catch (Parameter $e) {
                // expected
            }
        }

        try {
            $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, $wrong);
            $this->fail('the final wrong guess should have been refused');
        } catch (Locked $e) {
            // expected: this is the guess that exceeds the limit
        }

        $this->assertNull($this->cachedCode(), 'the code must not survive the limit being hit');
        $this->assertNull($this->cachedAttempts(), 'the counter is cleared along with the code');
    }

    /**
     * Once the code is burned, even the value that was correct is worthless - the
     * caller has to go through sendCode() again, which is where the resend lock
     * applies.
     */
    public function testABurnedCodeIsNoLongerUsable(): void
    {
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);
        $correct = $this->cachedCode();

        $this->burnCode($correct);

        $this->expectException(Miss::class);
        $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, $correct);
    }

    public function testRequestingANewCodeResetsTheCounter(): void
    {
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);
        $correct = $this->cachedCode();

        $this->burnCode($correct);

        // the code was burned, so a new one may be issued
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);

        $this->assertNotNull($this->cachedCode());
        $this->assertNull(
            $this->cachedAttempts(),
            'a fresh code must not inherit the previous one\'s failures'
        );
    }

    public function testTheCorrectCodeIsAcceptedAndClearsEverything(): void
    {
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);

        // one failure first, to prove a success clears the counter too
        try {
            $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, $this->cachedCode() + 1);
        } catch (Parameter $e) {
            // expected
        }
        $this->assertSame(1, $this->cachedAttempts());

        $this->assertTrue($this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, $this->cachedCode()));
        $this->assertNull($this->cachedCode());
        $this->assertNull($this->cachedAttempts());
    }

    /**
     * The counter is per code, so one account's failures must not affect another.
     */
    public function testAttemptsAreCountedPerAccount(): void
    {
        $otherUid = self::UID + 1;

        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, self::UID);
        $this->service->sendCode(VerificationCode::CHANGE_PASSWORD, $otherUid);

        try {
            $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, 1);
        } catch (Parameter $e) {
            // expected
        }

        $this->assertSame(1, $this->cachedAttempts());
        $this->assertNull($this->cachedAttempts($otherUid), 'the other account should be untouched');
    }

    public function testAnUnknownTypeIsStillRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->service->checkCode('not-a-real-type', self::UID, 123456);
    }

    /**
     * Keep guessing wrong until the code is burned.
     *
     * Takes the correct code and always submits a different one - passing the
     * correct value here would consume the code on the first call instead.
     */
    private function burnCode(int $correct): void
    {
        $wrong = $correct + 1;

        for ($attempt = 0; $attempt < VerificationCode::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::UID, $wrong);
                $this->fail('a wrong code was accepted');
            } catch (Locked $e) {
                return; // burned
            } catch (Parameter $e) {
                // keep going until it locks
            }
        }

        $this->fail('the code was never burned');
    }

    private function cachedCode(int $uid = self::UID): ?int
    {
        $item = $this->cache->getItem(sprintf(self::FORMAT, $uid));

        return $item->isHit() ? (int) $item->get() : null;
    }

    private function cachedAttempts(int $uid = self::UID): ?int
    {
        $item = $this->cache->getItem(sprintf(self::FORMAT, $uid) . '_attempts');

        return $item->isHit() ? (int) $item->get() : null;
    }
}
