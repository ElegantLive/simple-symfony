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
use App\Exception\Used;
use App\Message\SignUpNotification;
use App\Repository\UserRepository;
use App\Service\Request;
use App\Service\Token;
use App\Service\Serializer;
use App\Validator\Register;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
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
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * User constructor.
     * @param UserRepository         $userRepository
     * @param EntityManagerInterface $entityManager
     * @param MessageBusInterface    $bus
     */
    public function __construct (UserRepository $userRepository,
                                 EntityManagerInterface $entityManager,
                                 MessageBusInterface $bus)
    {
        $this->userRepository = $userRepository;
        $this->entityManager  = $entityManager;
        $this->bus            = $bus;
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

        if ($this->userRepository->findOneBy(['email' => $data['email']])) throw new Used(['message' => '邮箱已被占用']);
        if ($this->userRepository->findOneBy(['mobile' => $data['mobile']])) throw new Used(['message' => '号码已被占用']);
        if ($this->userRepository->findOneBy(['name' => $data['name']])) throw new Used(['message' => '昵称已被占用']);

        $user->setTrust(['avatar', 'email', 'mobile', 'sex', 'name']);
        $user->setTrustFields($data);

        $user->setRand();
        $user->setPassword($data['password']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

//        $this->bus->dispatch(new Envelope(new SignUpNotification($user->getId())), [
//            new DelayStamp(1000 * 30)
//        ]);
        $this->bus->dispatch((new SignUpNotification($user->getId())));

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