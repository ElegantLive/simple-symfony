<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 14:47
 */

namespace App\Controller;


use App\Exception\Miss;
use App\Exception\Success;
use App\Repository\UserRepository;
use App\Service\Request;
use App\Service\Token;
use App\Service\Serializer;
use App\Validator\Register;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/user")
 * Class User
 * @package App\Controller
 */
class User extends AbstractController
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * User constructor.
     * @param UserRepository         $userRepository
     * @param EntityManagerInterface $entityManager
     */
    public function __construct (UserRepository $userRepository, EntityManagerInterface $entityManager)
    {
        $this->userRepository = $userRepository;
        $this->entityManager  = $entityManager;
    }

    /**
     * @Route("/register", methods={"POST"}, name="userRegister")
     * @param Request $request
     * @throws \Exception
     */
    public function register (Request $request)
    {
        $data = $request->getData();
        (new Register())->check($data);

        $user = new \App\Entity\User();

        $user->setTrust(['avatar', 'email', 'mobile', 'sex', 'name']);
        $user->setTrustFields($data);

        $user->setRand();
        $user->setPassword($data['password']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        throw new Success();
    }

    /**
     * @Route("/info", methods={"GET"}, name="userInfo")
     * @param Token      $token
     * @param Serializer $serializer
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function info (Token $token, Serializer $serializer)
    {
        $id = $token->getCurrentTokenKey('id');

        $user = $this->userRepository->findOneBy(['id' => $id]);
        throw new \Exception('something was wrong');

        if (empty($user)) throw new Miss();

        throw new Success(['data' => $serializer->normalize($user, 'json', $user->filterHidden())]);
    }

    /**
     * @Route("/", methods={"PATCH"}, name="updateUser")
     * @param Token   $token
     * @param Request $request
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function update (Token $token, Request $request)
    {
        $id = $token->getCurrentTokenKey('id');

        $data = $request->getData();

        $user = $this->userRepository->findOneBy(['id' => $id]);

        if (!$user) throw new Miss();

        $user->setTrustFields($data);
        $this->entityManager->flush();

        throw new Success();
    }

    /**
     * @Route("/", methods={"DELETE"}, name="deleteUser")
     * @param Token $token
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function disable (Token $token)
    {
        $id = $token->getCurrentTokenKey('id');

        $user = $this->userRepository->findOneBy(['id' => $id]);

        if (!$user) throw new Miss();

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        throw new Success();
    }
}