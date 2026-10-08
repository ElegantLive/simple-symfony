<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 12:43
 */

namespace App\Service;


use App\Exception\Gone;
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
     * A valid bcrypt hash of a value nobody knows, verified against when the
     * request is going to fail anyway. See burnPasswordTime().
     *
     * It must be a *valid* hash: password_verify() returns false immediately for
     * a malformed one, which would defeat the whole point.
     */
    const DUMMY_HASH = '$2y$10$45/OFEAqsAR9mE4DeeaDKevqnRpFl55ckDpFNPo1RsEXC684XHt/W';

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
        $user = $this->userRepository->findOneBy($map);

        if (empty($user)) {
            $this->burnPasswordTime($data['password']);
            throw new TokenException(['message' => '账号错误']);
        }

        if ($user->isDeleted()) {
            $this->burnPasswordTime($data['password']);
            throw new Gone();
        }

        if (!$user->verifyPassword($data['password'])) {
            throw new TokenException(['message' => '密码错误']);
        }

//        return $this->token->generate(['id' => $user->getId()]); // cache token
        return $this->token->generateToken(['id' => $user->getId()]);
    }

    /**
     * Spend the same time a real verification would, on the paths that are going
     * to fail before reaching one.
     *
     * Passwords are now bcrypt, which costs ~200ms; an early return costs
     * nothing. Without this, response time alone tells a caller whether an
     * account exists and is active, which is exactly the enumeration oracle the
     * separate "账号错误" / "密码错误" messages already hint at. (The messages
     * themselves are left alone - the clients may depend on telling them apart.)
     *
     * @param string $attempt
     */
    private function burnPasswordTime (string $attempt): void
    {
        password_verify($attempt, self::DUMMY_HASH);
    }
}