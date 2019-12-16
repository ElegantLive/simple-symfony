<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 12:43
 */

namespace App\Service;


use App\Exception\Token as TokenException;
use App\Repository\UserRepository;

/**
 * Class UserToken
 * @package App\Service
 */
class UserToken
{
    /**
     * 作用域
     */
    const SCOPE = 16;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var Token
     */
    private $token;

    /**
     * UserToken constructor.
     * @param UserRepository $userRepository
     * @param Token          $token
     */
    public function __construct (UserRepository $userRepository, Token $token)
    {
        $this->userRepository = $userRepository;
        $this->token          = $token;
    }

    /**
     * @param array $data
     * @return string
     */
    public function getToken (array $data)
    {
        $map = ['mobile' => $data['mobile']];
        $res = $this->userRepository->findOneBy($map);
        if (empty($res)) throw new TokenException(['message' => '账号错误']);

        if ($res->getPassword() != $res->encodePassword($data['password'], $res->getRand())) {
            throw new TokenException(['message' => '密码错误']);
        }

        return $this->token->generate(['id' => $res->getId()]);
    }
}