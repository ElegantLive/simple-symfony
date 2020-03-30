<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 14:47
 */

namespace App\Controller;


use App\Entity\UserAvatarHistory;
use App\Exception\Done;
use App\Exception\Forbidden;
use App\Exception\Gone;
use App\Exception\Miss;
use App\Exception\Success;
use App\Exception\Used;
use App\Message\SignUpNotification;
use App\Repository\UserAvatarHistoryRepository;
use App\Repository\UserRepository;
use App\Service\Request;
use App\Service\Token;
use App\Service\Serializer;
use App\Service\VerificationCode;
use App\Validator\ChangePassword;
use App\Validator\Register;
use App\Validator\SetAvatar;
use Doctrine\ORM\EntityManagerInterface;
use Stof\DoctrineExtensionsBundle\Uploadable\UploadableManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

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
     * @var Serializer
     */
    private $serializer;
    /**
     * @var VerificationCode
     */
    private $code;
    /**
     * @var UserAvatarHistoryRepository
     */
    private $avatarHistoryRepository;

    /**
     * User constructor.
     * @param UserRepository              $userRepository
     * @param EntityManagerInterface      $entityManager
     * @param MessageBusInterface         $bus
     * @param Serializer                  $serializer
     * @param VerificationCode            $code
     * @param UserAvatarHistoryRepository $avatarHistoryRepository
     */
    public function __construct (UserRepository $userRepository,
                                 EntityManagerInterface $entityManager,
                                 MessageBusInterface $bus,
                                 Serializer $serializer,
                                 VerificationCode $code,
                                 UserAvatarHistoryRepository $avatarHistoryRepository)
    {
        $this->userRepository          = $userRepository;
        $this->entityManager           = $entityManager;
        $this->bus                     = $bus;
        $this->serializer              = $serializer;
        $this->code                    = $code;
        $this->avatarHistoryRepository = $avatarHistoryRepository;
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

        $user->setTrust(['email', 'mobile', 'sex', 'name']);
        $user->setTrustFields($data);

        $user->setRand();
        $user->setPassword($data['password']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->bus->dispatch(new SignUpNotification($user->getId()));

        throw new Success();
    }

    /**
     * @Route("/info/{id}", methods={"GET"}, name="userInfo")
     * @param int $id
     */
    public function info (int $id)
    {
        $user = $this->userRepository->find($id);

        if (empty($user)) throw new Miss();
        if ($user->isDeleted()) throw new Gone();

        throw new Success([
            'data' => $this->serializer->normalize($user, 'json', [
                AbstractNormalizer::ATTRIBUTES => $user->getNormal()
            ])
        ]);
    }

    /**
     * @Route("/self", methods={"GET"}, name="getSelfInfo")
     * @param Token $token
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSelf (Token $token)
    {
        $user = $token->getCurrentUser();

        throw new Success(['data' => $this->serializer->normalize($user, 'json', $user->filterHidden())]);
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
     * @Route("/password/code", methods={"GET"}, name="changePasswordCode")
     * @param Token $token
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function changePasswordCode (Token $token)
    {
        $uid = $token->getCurrentTokenKey('id');
        $this->code->sendCode($this->code::CHANGE_PASSWORD, $uid);

        throw new Success(['message' => '发送成功']);
    }

    /**
     * @Route("/password", methods={"PATCH"}, name="changePassword")
     * @param Token   $token
     * @param Request $request
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function password (Token $token, Request $request)
    {
        $uid  = $token->getCurrentTokenKey('id');
        $data = $request->getData();

        (new ChangePassword())->check($data);
        $this->code->checkCode($this->code::CHANGE_PASSWORD, $uid, $data['code']);

        $user = $this->userRepository->find($uid);

        $user->setRand();
        $user->setPassword($data['password']);

        $this->entityManager->flush();

        $token->cleanToken();

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
        if ($user->isDeleted()) throw new Gone();

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        throw new Success();
    }

    /**
     * @Route("/avatar/upload", methods={"POST"}, name="setAvatarByUpload", )
     * @param Token             $token
     * @param Request           $request
     * @param UploadableManager $uploadableManager
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function setAvatarByUpload (Token $token, Request $request, UploadableManager $uploadableManager)
    {
        $data = $request->request->files->all();

        (new SetAvatar())->check($data);

        $id = $token->getCurrentTokenKey('id');

        $user = $this->userRepository->findOneBy(['id' => $id]);

        if (empty($user)) throw new Miss();

        $avatarEntity = new UserAvatarHistory();

        $this->entityManager->beginTransaction();
        try {
            $oldCurrent = $this->avatarHistoryRepository->findOneBy(['current' => true]);
            if ($oldCurrent) {
                $oldCurrent->setCurrent(false);
                $this->entityManager->flush();
            }

            $uploadableManager->markEntityToUpload($avatarEntity, $data['avatar']);
            $avatarEntity->setCurrent(true);
            $avatarEntity->setUser($user);

            $this->entityManager->persist($avatarEntity);
            $this->entityManager->flush();

            $user->setAvatar($avatarEntity->getPublicPath());
            $this->entityManager->flush();

            $this->entityManager->commit();
        } catch (\Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
        throw new Success();
    }

    /**
     * @Route("/avatar/history", methods={"PUT"}, name="setAvatarByHistory", )
     * @param Token   $token
     * @param Request $request
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function setAvatarByHistory (Token $token, Request $request)
    {
        $user = $token->getCurrentUser();

        $data = $request->getData();

        $this->entityManager->beginTransaction();
        try {
            $oldCurrent = $this->avatarHistoryRepository->findOneBy(['current' => true]);
            $oldCurrent->setCurrent(false);

            $newCurrent = $this->avatarHistoryRepository->find($data['id']);
            if (empty($newCurrent)) throw new Miss(['message' => '历史头像失效']);
            if ($newCurrent->isDeleted()) throw new Gone(['message' => '历史头像失效']);
            if ($newCurrent->getUser()->getId() !== $user->getId()) throw new Forbidden(['message' => '无权操作']);
            if (true === $newCurrent->getCurrent()) throw new Done();

            $newCurrent->setCurrent(true);
            $user->setAvatar($newCurrent->getPublicPath());

            $this->entityManager->flush();

            $this->entityManager->commit();
        } catch (\Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        throw new Success();
    }

    /**
     * @Route("/avatar/history", methods={"GET"}, name="getAvatarHistory")
     * @param Token $token
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function avatarHistory (Token $token)
    {
        $user = $token->getCurrentUser();

        $list = $this->avatarHistoryRepository->findBy(['user' => $user]);

        $data = [];
        foreach ($list as $item) {
            if ($item->isDeleted()) continue;
            array_push($data, $this->serializer->normalize($item, 'json', [AbstractNormalizer::ATTRIBUTES => $item->getNormal()]));
        }

        throw new Success(['data' => $data]);
    }
}