<?php

namespace App\Controller;

use App\Exception\Success;
use App\Service\UserToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
     */
    public function user (Request $request, UserToken $userToken)
    {
        $data = $request->request->all();

        (new \App\Validator\UserToken())->check($data);

        $token = $userToken->getToken($data);

        throw new Success(['data' => [
            'token' => $token
        ]]);
    }

    /**
     * @param \App\Service\Token $token
     */
    public function logout (\App\Service\Token $token)
    {
        $token->cleanToken();

        throw new Success();
    }
}
