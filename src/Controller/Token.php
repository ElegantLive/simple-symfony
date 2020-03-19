<?php

namespace App\Controller;

use App\Exception\Success;
use App\Service\Request;
use App\Service\Token as TokenService;
use App\Service\UserToken;
use App\Validator\UserToken as UserTokenValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/token")
 * Class Token
 * @package App\Controller
 */
class Token extends AbstractController
{
    /**
     * @Route("/user", methods={"POST"})
     * @param Request   $request
     * @param UserToken $userToken
     * @throws \Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function user (Request $request, UserToken $userToken)
    {
        $data = $request->getData();

        (new UserTokenValidator())->check($data);

        $token = $userToken->getToken($data);

        throw new Success(['data' => [
            'token' => $token
        ]]);
    }

    /**
     * @param TokenService $token
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function logout (TokenService $token)
    {
        $token->cleanToken();

        throw new Success();
    }
}
