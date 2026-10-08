<?php

namespace App\Tests\Service;

use App\Exception\Locked;
use App\Exception\Miss;
use App\Exception\Parameter;
use App\Message\VerificationCodeNotification;
use App\Service\VerificationCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * App\Service\VerificationCode, including the register flow.
 *
 * The code is read back out of the notification the service dispatches rather
 * than out of the cache. That is the more useful contract to pin: it asserts what
 * the caller is told to mail and to whom, which is exactly what was broken before
 * (the recipient was looked up from a uid, so registration mailed nobody).
 *
 * An in-memory cache and a bus with no handler are enough; nothing here needs
 * Redis, a worker or a mail round trip.
 */
final class VerificationCodeTest extends TestCase
{
    private const EMAIL   = 'someone@example.com';
    private const NAME    = 'Someone';
    private const SUBJECT = 'someone@example.com';

    /** @var ArrayAdapter */
    private $cache;

    /** @var CapturingBusMiddleware */
    private $bus;

    /** @var VerificationCode */
    private $service;

    protected function setUp(): void
    {
        $this->cache   = new ArrayAdapter();
        $this->bus     = new CapturingBusMiddleware();
        $this->service = new VerificationCode($this->cache, new MessageBus([$this->bus]), 'from@example.com');
    }

    public function testSendingACodeNotifiesTheGivenRecipient(): void
    {
        $this->service->sendCode(VerificationCode::REGISTER, self::SUBJECT, self::EMAIL, self::NAME);

        $notification = $this->bus->last();
        $this->assertNotNull($notification, 'no notification was dispatched');
        $this->assertSame(VerificationCode::REGISTER, $notification->getType());
        $this->assertSame(self::EMAIL, $notification->getEmail(), 'the recipient must travel with the message');
        $this->assertSame(self::NAME, $notification->getName());
        $this->assertSame('from@example.com', $notification->getFrom());
        $this->assertSame(720, $notification->getTime(), 'register codes live for 720 seconds');

        $code = $notification->getCode();
        $this->assertGreaterThanOrEqual(200000, $code);
        $this->assertLessThanOrEqual(999999, $code);
    }

    /**
     * Registration has no account yet, so there is no name to greet by. The
     * notification has to allow that rather than requiring one.
     */
    public function testTheRecipientNameMayBeOmitted(): void
    {
        $this->service->sendCode(VerificationCode::REGISTER, self::SUBJECT, self::EMAIL);

        $this->assertSame('', $this->bus->last()->getName());
    }

    public function testASecondCodeCannotBeRequestedWhileOneIsLive(): void
    {
        $this->issue();

        $this->expectException(Locked::class);
        $this->service->sendCode(VerificationCode::REGISTER, self::SUBJECT, self::EMAIL);
    }

    public function testWrongGuessesBelowTheLimitAreRejectedButKeepTheCode(): void
    {
        $code = $this->issue();

        for ($attempt = 1; $attempt < VerificationCode::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code + 1);
                $this->fail('a wrong code was accepted');
            } catch (Parameter $e) {
                // expected
            }
        }

        $this->assertTrue(
            $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code),
            'the code should still work once the guesses stop, below the limit'
        );
    }

    public function testTheLastAllowedGuessBurnsTheCode(): void
    {
        $code = $this->issue();

        for ($attempt = 1; $attempt < VerificationCode::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code + 1);
            } catch (Parameter $e) {
                // expected until the limit
            }
        }

        try {
            $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code + 1);
            $this->fail('the final wrong guess should have been refused');
        } catch (Locked $e) {
            // expected: this guess exceeds the limit
        }

        $this->expectException(Miss::class);
        $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code);
    }

    public function testRequestingANewCodeAfterABurnResetsTheAttempts(): void
    {
        $code = $this->issue();

        // burn it
        for ($attempt = 0; $attempt < VerificationCode::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code + 1);
            } catch (Locked $e) {
                break;
            } catch (Parameter $e) {
                // keep going
            }
        }

        $this->service->sendCode(VerificationCode::REGISTER, self::SUBJECT, self::EMAIL, self::NAME);
        $fresh = $this->bus->last()->getCode();

        // One wrong guess against the new code must not immediately lock it, which
        // it would if the old counter had survived.
        try {
            $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $fresh + 1);
        } catch (Parameter $e) {
            // expected
        }

        $this->assertTrue($this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $fresh));
    }

    public function testACorrectCodeIsAcceptedOnlyOnce(): void
    {
        $code = $this->issue();

        $this->assertTrue($this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code));

        $this->expectException(Miss::class);
        $this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code);
    }

    /**
     * Register codes are keyed by address, so one address's code must not open
     * another's registration - nor the same address's password change.
     */
    public function testCodesAreIsolatedPerSubjectAndPerType(): void
    {
        $code = $this->issue();

        try {
            $this->service->checkCode(VerificationCode::REGISTER, 'other@example.com', $code);
            $this->fail('a code issued for one address was accepted for another');
        } catch (Miss $e) {
            // expected
        }

        try {
            $this->service->checkCode(VerificationCode::CHANGE_PASSWORD, self::SUBJECT, $code);
            $this->fail('a register code was accepted as a password-change code');
        } catch (Miss $e) {
            // expected
        }

        $this->assertTrue($this->service->checkCode(VerificationCode::REGISTER, self::SUBJECT, $code));
    }

    /**
     * The address reaches the service twice by different routes - query string
     * when the code is requested, request body when it is redeemed - so casing and
     * padding have to be normalised or a user typing it slightly differently gets
     * "请获取验证码" for a code that was issued.
     */
    public function testTheSubjectIsNormalised(): void
    {
        $this->service->sendCode(VerificationCode::REGISTER, '  MiXeD@Example.COM  ', self::EMAIL);
        $code = $this->bus->last()->getCode();

        $this->assertTrue($this->service->checkCode(VerificationCode::REGISTER, 'mixed@example.com', $code));
    }

    public function testAnUnknownTypeIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->service->checkCode('not-a-real-type', self::SUBJECT, 123456);
    }

    /**
     * Issue a code and return it.
     */
    private function issue(string $type = VerificationCode::REGISTER, string $subject = self::SUBJECT): int
    {
        $this->service->sendCode($type, $subject, self::EMAIL, self::NAME);

        return $this->bus->last()->getCode();
    }
}

/**
 * Collects the notifications the service dispatches.
 *
 * handle() returns without calling $stack->next() on purpose: there is no handler
 * in these tests, and ending the chain here keeps the bus a pure recorder.
 */
final class CapturingBusMiddleware implements MiddlewareInterface
{
    /** @var object[] */
    public $messages = [];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->messages[] = $envelope->getMessage();

        return $envelope;
    }

    public function last(): ?VerificationCodeNotification
    {
        $last = end($this->messages);

        return $last instanceof VerificationCodeNotification ? $last : null;
    }
}
