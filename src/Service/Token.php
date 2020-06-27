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
use App\Repository\UserRepository;
use Faker\Factory;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Psr\Cache\InvalidArgumentException as InvalidArgumentExceptionAlias;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * Class Token
 *
 * @package App\Service
 */
class Token
{
    /**
     * 作用域
     *
     * @var
     */
    const SCOPE = 16;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var AdapterInterface
     */
    private $cache;

    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var string
     */
    private $privatePem;
    /**
     * @var string
     */
    private $publicPem;
    /**
     * @var string
     */
    private $projectDirectory;
    /**
     * @var string
     */
    private $type;
    /**
     * @var int
     */
    private $expire;

    /**
     * Token constructor.
     *
     * @param Request          $request
     * @param AdapterInterface $cache
     * @param UserRepository   $userRepository
     * @param string           $public
     * @param string           $private
     * @param string           $projectDirectory
     * @param string           $type
     * @param int              $expire
     */
    public function __construct (Request $request,
                                 AdapterInterface $cache,
                                 UserRepository $userRepository,
                                 string $public,
                                 string $private,
                                 string $projectDirectory,
                                 string $type,
                                 int $expire)
    {
        $this->request          = $request;
        $this->cache            = $cache;
        $this->userRepository   = $userRepository;
        $this->privatePem       = $private;
        $this->publicPem        = $public;
        $this->projectDirectory = $projectDirectory;
        $this->type             = $type;
        $this->expire           = $expire;
    }

    /**
     * @param bool $decode
     * @return string|string[]
     */
    private function getType ($decode = false)
    {
        return $decode ? [$this->type] : $this->type;
    }

    /**
     * @param bool $decode
     * @return false|string
     */
    private function getPem ($decode = false)
    {
        $pem = $decode ? $this->publicPem : $this->privatePem;

        return file_get_contents($this->projectDirectory . $pem);
//        $file = $this->projectDirectory . $pem;
//        return $decode ? openssl_get_privatekey($file): openssl_get_publickey($file);
    }

//    /**
//     * @return AdapterInterface
//     */
//    private function getCache ()
//    {
//        return $this->cache;
//    }
//
//    /**
//     * @throws InvalidArgumentExceptionAlias
//     */
//    public function cleanToken ()
//    {
//        self::getCache()->deleteItem(self::getTokenFromRequest());
//    }

    /**
     * @param array $var
     * @param int   $scope
     * @return string
     */
    public function generateToken (array $var, int $scope = self::SCOPE)
    {
        $data = ['scope' => $scope];

        $var = array_merge($var, $data);

        $currentTime = time();
        $expireTime  = bcadd($currentTime, $this->expire);

        // jwt 基础信息
        $payload = [
            'iss' => '',
            'aud' => '',
            'sub' => '',
            'jti' => '',
            'iat' => $currentTime,
            'nbf' => $currentTime,
            'exp' => $expireTime,
            'var' => $var
        ];

        return JWT::encode($payload, self::getPem(), self::getType());
    }

//    /**
//     * cache token
//     *
//     * @param array $var
//     * @param int   $scope
//     * @return string
//     * @throws InvalidArgumentExceptionAlias
//     */
//    public function generate (array $var, int $scope = self::SCOPE)
//    {
//        $data = [
//            'expire' => time(),
//            'scope'  => $scope,
//        ];
//
//        $var = array_merge($var, $data);
//
//        $token     = self::generateKey();
//        $tokenItem = self::getCache()->getItem($token);
//        $tokenItem->set($var);
//        $tokenItem->expiresAfter($this->expire);
//
//        self::getCache()->save($tokenItem);
//
//        return $token;
//    }

    /**
     * @return string
     */
    private function generateKey ()
    {
        return Factory::create()->md5;
    }

    /**
     * 生成jwt
     *
     * @param string $key
     * @return mixed
     */
    public function getCurrentTokenKey (string $key)
    {
        $token = self::getTokenFromRequest();

        try {
            $payload = JWT::decode($token, self::getPem(true), self::getType(true));
        } catch (\Throwable $throwable) {
            $expire = false;
            if ($throwable instanceof ExpiredException) {
                $expire = true;
            }

            $exception = [
                'message'   => $expire ? 'token已过期' : 'token已失效',
                'code'      => 401,
                'errorCode' => $expire ? 10008 : 10001
            ];
            throw new TokenException($exception);
//            throw $throwable; // 调试使用
        }

        $var = (array)$payload->var;

        if (isset($var[$key])) return $var[$key];

        throw new TokenException(['message' => '尝试获取的key不存在']);
    }

//    /**
//     * cache token
//     *
//     * @param string $key
//     * @return mixed
//     * @throws \Psr\Cache\InvalidArgumentException
//     */
//    public function getCurrentTokenKey (string $key)
//    {
//        $item = static::getCache()->getItem(self::getTokenFromRequest());
//        if (empty($item->isHit())) throw new TokenException(['message' => 'token已失效']);
//
//        $var = $item->get();
//        // throw new TokenException(['data' => $var]);
//        if (isset($var[$key])) return $var[$key];
//
//        throw new TokenException(['message' => '尝试获取的key不存在']);
//    }

    /**
     * @return \App\Entity\User|null
     */
    public function getCurrentUser ()
    {
        $userId = self::getCurrentTokenKey('id');

        $user = $this->userRepository->find($userId);
        if (empty($user)) throw new TokenException(['message' => '请重新登录']);
        if ($user->isDeleted()) throw new TokenException(['message' => '用户已注销']);

        return $user;
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

//    /**
//     * @return null|string|string[]
//     */
//    private function getTokenFromRequest ()
//    {
//        $token = $this->request->getRequest()->headers->get('token');
//        if (empty($token)) throw new TokenException(['message' => '尝试获取的token不存在']);
//
//        return $token;
//    }

    /**
     * @return null|string|string[]
     */
    private function getTokenFromRequest ()
    {
        $token = $this->request->getRequest()->headers->get('authorization');
        if (empty($token)) throw new TokenException(['message' => '尝试获取的token不存在']);

        $token = str_replace('Bearer ', '', $token);

        return $token;
    }
}