<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 12:43
 */

namespace App\Service;


use App\Exception\Forbidden;
use App\Exception\Token as TokenException;
use Faker\Factory;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Class Token
 * @package App\Service
 */
class Token
{
    /**
     * 作用域
     * @var
     */
    const SCOPE = 16;

    /**
     * @var RedisAdapter;
     */
    private static $cache;

    /**
     * @var Request
     */
    private $request;

    /**
     * Token constructor.
     * @param Request $request
     */
    public function __construct (Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return RedisAdapter
     */
    private static function getCache ()
    {
        if ((Token::$cache instanceof RedisAdapter) == false) {
            $client       = RedisAdapter::createConnection('redis://192.168.10.10:6379');
            Token::$cache = new RedisAdapter($client);
        }
        return Token::$cache;
    }

    public function cleanToken ()
    {
        self::getCache()->deleteItem(self::getTokenFromRequest());
    }

    /**
     * @param array $var
     * @param int   $scope
     * @return string
     */
    public function generate (array $var, int $scope = self::SCOPE)
    {
        $data = [
            'expire' => time(),
            'scope'  => $scope,
        ];

        $var = array_merge($var, $data);

        $token     = self::generateKey();
        $tokenItem = self::getCache()->getItem($token);
        $tokenItem->set($var);
        $tokenItem->expiresAfter(3000);

        self::getCache()->save($tokenItem);

        return $token;
    }

    /**
     * @return string
     */
    private function generateKey ()
    {
        return Factory::create()->md5;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getCurrentTokenKey (string $key)
    {
        $item = static::getCache()->getItem(self::getTokenFromRequest());
        if (empty($item->isHit())) throw new TokenException(['message' => 'token已失效']);

        $var = $item->get();
        if (isset($var[$key])) return $var[$key];

        throw new TokenException(['message' => '尝试获取的key不存在']);
    }

    /**
     * 检查当前 token 是否失效
     */
    public function checkToken ()
    {
        self::authentication(self::SCOPE);
    }

    /**
     * @param int $scope
     */
    public function authentication (int $scope)
    {
        $varScope = self::getCurrentTokenKey('scope');

        if ($varScope != $scope) throw new Forbidden();
    }

    /**
     * @return null|string|string[]
     */
    private function getTokenFromRequest ()
    {
        $token = $this->request->getRequest()->headers->get('token');
        if (empty($token)) throw new TokenException(['message' => '尝试获取的key不存在']);

        return $token;
    }
}