<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 12:43
 */

namespace App\Service;


use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserToken extends Token
{
    const SCOPE = 16;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct (UserRepository $userRepository, EntityManagerInterface $entityManager)
    {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    public function getToken (array $data)
    {
        $map = ['mobile' => $data['mobile']];
        $res = $this->userRepository->findOneBy($map);
        if (empty($res)) throw new \App\Exception\Token(['message' => '账号错误']);

        if ($res->getPassword() != $res->encodePassword($data['password'], $res->getRand())){
            throw new \App\Exception\Token(['message' => '密码错误']);
        }

        $token = self::generate([
            'id' => $res->getId()
        ]);

        return $token;
    }
}