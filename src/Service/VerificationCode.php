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

        $code = rand(200000, 999999);

        $time = $this->getExpires($type);
        $from = $this->from;
        $item->set($code);
        $item->expiresAfter($time);

        $this->cache->save($item);

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

        if (intval($item->get()) !== $code) throw new Parameter(['message' => '验证码错误']);

        $this->cache->deleteItem(sprintf($format, $uid));
        return true;
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