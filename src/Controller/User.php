<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 14:47
 */

namespace App\Controller;


use App\Exception\Success;
use App\Repository\UserRepository;
use App\Validator\Register;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/register", methods={"POST"})
     * @param Request          $request
     * @throws \Exception
     */
    public function register (Request $request)
    {
        $data = $request->request->all();
        (new Register())->check($data);

        $user = new \App\Entity\User();

        $user->setAvatar($data['avatar']);
        $user->setEmail($data['email']);
        $user->setMobile($data['mobile']);
        $user->setSex($data['sex']);
        $user->setName($data['name']);
        $user->setRand();
        $user->setPassword($data['password']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        throw new Success();
    }
}