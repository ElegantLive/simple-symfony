<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 12:43
 */

namespace App\Service;


use App\Exception\Forbidden;
use Faker\Factory;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Token
 * @package App\Service
 */
abstract class Token
{
    /**
     * 作用域
     */
    const SCOPE = 16;

    /**
     * @var RedisAdapter;
     */
    private static $cache;

    /**
     * @return RedisAdapter
     */
    private static function getCache ()
    {
        if ((Token::$cache instanceof RedisAdapter) == false) {
            $client = RedisAdapter::createConnection(
                'redis://localhost:6379'
            );
            Token::$cache = new RedisAdapter($client);
        }
        return Token::$cache;
    }

    /**
     * @param array $data
     * @return mixed
     */
    abstract public function getToken (array $data);

    public function cleanToken () {
        self::getCache()->deleteItem(self::getTokenFromRequest());
    }

    /**
     * @param array $var
     * @return string
     */
    protected function generate (array $var)
    {
        $data = [
            'expire' => time(),
            'scope' => static::SCOPE,
        ];

        $var = array_merge($var, $data);

        $token = self::generateKey();
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
    public static function getCurrentTokenKey (string $key)
    {
        $item = static::getCache()->getItem(self::getTokenFromRequest());
        if (empty($item->isHit())) throw new \App\Exception\Token();

        $var = $item->get();
        if (isset($var[$key])) return $var[$key];

        throw new \App\Exception\Token(['message' => '尝试获取的key不存在']);
    }

    /**
     * @param string $type
     */
    public static function authentication (string $type)
    {
        $scope = self::getIdentity($type);
        $varScope = self::getCurrentTokenKey('scope');

        if ($varScope != $scope) throw new Forbidden();
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected static function getIdentity(string $key)
    {
        $identityArr = [
            'user' => UserToken::SCOPE,
        ];

        if (array_key_exists($key, $identityArr)) {
            return $identityArr[$key];
        }

        throw new \App\Exception\Token([
            'message' => '校验的身份不存在',
            'errorCode' => 10002
        ]);
    }

    /**
     * @return null|string|string[]
     */
    private static function getTokenFromRequest ()
    {
        $request = Request::createFromGlobals();

        $token = $request->headers->get('token');
        if (empty($token)) throw new \App\Exception\Token();

        return $token;
    }
}