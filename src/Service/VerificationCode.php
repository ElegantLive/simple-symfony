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
     * lived. A single client would have to sustain ~1,100 requests/second
     * (register, 720s) or ~2,700 (password change, 300s) to get through that, so
     * it is not a trivial attack, but nothing at all stood in the way of one that
     * could.
     *
     * The two flows carry very different exposure, so do not read this limit as
     * equally important to both:
     *
     *   CHANGE_PASSWORD  both call sites take the uid from the caller's own JWT,
     *                    so a guesser must already hold the victim's token.
     *   REGISTER         both call sites are unauthenticated - anyone can request
     *                    a code for any address, and the limit here is the only
     *                    thing bounding guesses against it.
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
     * Issue a code for $subject and mail it to $email.
     *
     * $subject is what the code is bound to, and what is held to it later: the
     * account uid when changing a password, the email address when registering
     * (there is no account yet). It is only ever used to build a cache key.
     *
     * $email and $name are the recipient. They travel with the notification
     * rather than being looked up from it, because on the register path there is
     * no user row to look up yet - the handler used to resolve the recipient from
     * the uid and silently send nothing when that failed.
     *
     * @param string $type
     * @param string $subject
     * @param string $email
     * @param string $name
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function sendCode (string $type, string $subject, string $email, string $name = '')
    {
        $key  = $this->cacheKey($type, $subject);
        $item = $this->cache->getItem($key);

        // One live code per subject. Note the flip side on the register path,
        // which is unauthenticated: whoever asks first decides when the next code
        // may be requested, so a third party can hold an address's slot for the
        // whole TTL. Bounding that needs rate limiting, which this project has
        // none of anywhere.
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
        $this->cache->deleteItem($this->attemptsKey($type, $subject));

        $this->bus->dispatch(new VerificationCodeNotification(compact('type', 'email', 'name', 'code', 'from', 'time')));
    }

    /**
     * @param string $type
     * @param string $subject
     * @param int    $code
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function checkCode (string $type, string $subject, int $code)
    {
        $item = $this->cache->getItem($this->cacheKey($type, $subject));
        if (empty($item->isHit())) throw new Miss(['message' => '请获取验证码']);

        if (intval($item->get()) !== $code) {
            $this->countFailedAttempt($type, $subject, $this->getExpires($type));
            throw new Parameter(['message' => '验证码错误']);
        }

        $this->cache->deleteItem($this->cacheKey($type, $subject));
        $this->cache->deleteItem($this->attemptsKey($type, $subject));
        return true;
    }

    /**
     * Record a wrong guess and throw the code away once there have been too many.
     *
     * @param string    $type
     * @param string    $subject
     * @param float|int $ttl
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function countFailedAttempt (string $type, string $subject, $ttl): void
    {
        $key      = $this->attemptsKey($type, $subject);
        $attempts = $this->cache->getItem($key);
        $count    = ($attempts->isHit() ? (int) $attempts->get() : 0) + 1;

        if ($count >= self::MAX_ATTEMPTS) {
            // Burn the code so guessing cannot continue; the caller has to go
            // through sendCode() again, which is where the resend lock applies.
            $this->cache->deleteItem($this->cacheKey($type, $subject));
            $this->cache->deleteItem($key);

            throw new Locked(['message' => '验证码错误次数过多，请重新获取']);
        }

        $attempts->set($count);
        // Never outlive the code it belongs to, or it would suppress the next one.
        $attempts->expiresAfter($ttl);
        $this->cache->save($attempts);
    }

    /**
     * The cache entry a subject's code lives in.
     *
     * The subject is hashed rather than embedded. PSR-6 reserves {}()/\@: in keys
     * and an email address contains '@', so putting it in raw makes the cache
     * adapter throw; hashing also bounds the key length, which an address does
     * not. The cost is that the key is no longer readable in Redis.
     *
     * @param string $type
     * @param string $subject
     * @return string
     * @throws \Exception
     */
    private function cacheKey (string $type, string $subject): string
    {
        $format = $this->getType($type);
        if (empty($format)) throw new \Exception('验证码type错误');

        // Trimmed and lower-cased before hashing. On the register path the subject
        // is an email address and it arrives from two different places: the query
        // string when the code is requested, the request body when it is redeemed.
        // Normalising here means "A@Example.com" and "a@example.com" share one
        // code, which is what someone typing their address twice expects. A no-op
        // for the uid the password-change path passes.
        return sprintf($format, hash('sha256', strtolower(trim($subject))));
    }

    /**
     * @param string $type
     * @param string $subject
     * @return string
     * @throws \Exception
     */
    private function attemptsKey (string $type, string $subject): string
    {
        return $this->cacheKey($type, $subject) . '_attempts';
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
