<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/26
 * Time: 18:28
 */

namespace App\Service;


use App\Exception\Locked;
use App\Exception\Miss;
use App\Exception\Parameter;
use App\Message\VerificationCodeNotification;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class VerificationCode
{
    const REGISTER        = 'register';
    const CHANGE_PASSWORD = 'changePassword';

    /**
     * How many wrong guesses a code tolerates before it is thrown away and the
     * caller has to request a new one.
     *
     * The code is six digits, so 800,000 values, and checkCode() used to leave
     * the cached code in place on every failure with nothing counting the
     * attempts - the whole code space could be walked for as long as the code
     * lived (300s for a password change). A single client would have to sustain
     * ~2,700 requests/second to get through that, so it is not a trivial attack,
     * but nothing at all stood in the way of one that could.
     *
     * Note the two things that already limited this, so the fix is not
     * over-stated: both call sites require a valid JWT for the account being
     * changed, and sendCode() refuses to issue a second code while one is still
     * live. It is a second-confirmation step, not a pre-auth one.
     */
    const MAX_ATTEMPTS = 5;

    /**
     * @var AdapterInterface
     */
    private $cache;

    private $type = [
        self::REGISTER        => 'register_%s',
        self::CHANGE_PASSWORD =>'change_password_%s',
    ];

    /**
     * 默认300秒过期
     * @var float|int
     */
    private $expires = 300;

    private $expiresMap = [
        self::REGISTER        => 720,
        self::CHANGE_PASSWORD => 300,
    ];
    /**
     * @var string
     */
    private $from;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * VerificationCode constructor.
     * @param AdapterInterface    $cache
     * @param MessageBusInterface $bus
     * @param                     $from
     */
    public function __construct (AdapterInterface $cache, MessageBusInterface $bus, $from)
    {
        $this->cache = $cache;
        $this->from  = $from;
        $this->bus   = $bus;
    }

    public function getType (string $type)
    {
        return array_key_exists($type, $this->type) ? $this->type[$type] : false;
    }

    /**
     * @param string $type
     * @param int    $uid
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function sendCode (string $type, int $uid)
    {
        $format = $this->getType($type);
        if (empty($format)) throw new \Exception('验证码type错误');
        $item = $this->cache->getItem(sprintf($format, $uid));

        if ($item->isHit()) throw new Locked(['message' => '验证码已发送，请稍后再试']);

        // random_int() rather than rand(): this is a security code, and rand() is
        // not a cryptographically secure generator. (Its predictability is hard to
        // exploit here - nothing in the project exposes a PHP rand() output, only
        // MySQL's RAND() in a query - but there is no reason to use the weaker one.)
        $code = random_int(200000, 999999);

        $time = $this->getExpires($type);
        $from = $this->from;
        $item->set($code);
        $item->expiresAfter($time);

        $this->cache->save($item);

        // A fresh code starts with a clean slate: without this, an earlier code's
        // failures would carry over and the new one could be locked before it was
        // ever tried.
        $this->cache->deleteItem($this->attemptsKey($format, $uid));

        $this->bus->dispatch(new VerificationCodeNotification(compact('type', 'uid', 'code', 'from', 'time')));
    }

    /**
     * @param string $type
     * @param int    $uid
     * @param int    $code
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function checkCode (string $type, int $uid, int $code)
    {
        $format = $this->getType($type);
        if (empty($format)) throw new \Exception('验证码type错误');
        $item = $this->cache->getItem(sprintf($format, $uid));
        if (empty($item->isHit())) throw new Miss(['message' => '请获取验证码']);

        if (intval($item->get()) !== $code) {
            $this->countFailedAttempt($format, $uid, $this->getExpires($type));
            throw new Parameter(['message' => '验证码错误']);
        }

        $this->cache->deleteItem(sprintf($format, $uid));
        $this->cache->deleteItem($this->attemptsKey($format, $uid));
        return true;
    }

    /**
     * Record a wrong guess and throw the code away once there have been too many.
     *
     * @param string    $format
     * @param int       $uid
     * @param float|int $ttl
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function countFailedAttempt (string $format, int $uid, $ttl): void
    {
        $key      = $this->attemptsKey($format, $uid);
        $attempts = $this->cache->getItem($key);
        $count    = ($attempts->isHit() ? (int) $attempts->get() : 0) + 1;

        if ($count >= self::MAX_ATTEMPTS) {
            // Burn the code so guessing cannot continue; the caller has to go
            // through sendCode() again, which is where the resend lock applies.
            $this->cache->deleteItem(sprintf($format, $uid));
            $this->cache->deleteItem($key);

            throw new Locked(['message' => '验证码错误次数过多，请重新获取']);
        }

        $attempts->set($count);
        // Never outlive the code it belongs to, or it would suppress the next one.
        $attempts->expiresAfter($ttl);
        $this->cache->save($attempts);
    }

    /**
     * @param string $format
     * @param int    $uid
     * @return string
     */
    private function attemptsKey (string $format, int $uid): string
    {
        return sprintf($format, $uid) . '_attempts';
    }

    /**
     * @param string $type
     * @return float|int
     */
    public function getExpires (string $type)
    {
        return array_key_exists($type, $this->expiresMap) ? $this->expiresMap[$type] : $this->expires;
    }
}