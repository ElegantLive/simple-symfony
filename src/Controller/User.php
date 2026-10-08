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
use App\Validator\RegisterCode;
use App\Validator\SetAvatar;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Stof\DoctrineExtensionsBundle\Uploadable\UploadableManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * @Route("/user")
 * Class User
 *
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
     *
     * @param UserRepository              $userRepository
     * @param EntityManagerInterface      $entityManager
     * @param MessageBusInterface         $bus
     * @param Serializer                  $serializer
     * @param VerificationCode            $code
     * @param UserAvatarHistoryRepository $avatarHistoryRepository
     */
    public function __construct(UserRepository $userRepository,
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
     * @throws Exception
     */
    public function register(Request $request)
    {
        $data = $request->getData();
        (new Register())->check($data);

        $user = new \App\Entity\User();

        if ($this->userRepository->findOneBy(['email' => $data['email']])) throw new Used(['message' => '邮箱已被占用']);
        if ($this->userRepository->findOneBy(['mobile' => $data['mobile']])) throw new Used(['message' => '号码已被占用']);
        if ($this->userRepository->findOneBy(['name' => $data['name']])) throw new Used(['message' => '昵称已被占用']);

        // Proves the address belongs to whoever is registering. Deliberately after
        // the uniqueness checks: checkCode() consumes the code when it succeeds, so
        // running it first would burn a valid code on a request that was only ever
        // going to fail with "邮箱已被占用".
        $this->code->checkCode($this->code::REGISTER, $data['email'], (int) $data['code']);

        $user->setTrust(['email', 'mobile', 'sex', 'name']);
        $user->setTrustFields($data);

        $user->setPassword($data['password']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->bus->dispatch(new SignUpNotification($user->getId()));

        throw new Success();
    }

    /**
     * @Route("/info/rand", methods={"GET"}, name="randInfo")
     * @throws NonUniqueResultException
     */
    public function randInfo()
    {
        $qb = $this->userRepository->createQueryBuilder('t');

        $minId = $qb->select('min(t.id)')->getQuery()->getSingleScalarResult();
        $maxId = $qb->select('max(t.id)')->getQuery()->getSingleScalarResult();

        // An empty table makes min() and max() null, and bcsub(null, null) below
        // then produced a predicate that matched nothing, so $randItem was null and
        // the dereference on it was a 500.
        if (null === $minId || null === $maxId) throw new Success(['data' => []]);

        // How many ids there are to hand out. The loop below only stops once it has
        // collected three DISTINCT ids, so asking for three while two accounts
        // exist never terminated: the request hung until PHP's max_execution_time
        // killed it, and because the built-in server is single-threaded it took
        // every later request with it.
        $available = (int) $this->userRepository->createQueryBuilder('t')
            ->select('count(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($available < 1) throw new Success(['data' => []]);

        $randNum = min(3, $available);

        $results = [];
        $randQb  = $this->userRepository->createQueryBuilder('y');

        // Bounded as well as capped: random ids over a table with gaps (soft
        // deletes leave them) can keep landing on a row that is already in
        // $results, or on nothing at all.
        $attempts = 0;

        while (count($results) < $randNum) {
            if (++$attempts > 100) break;

            $randItem = $randQb->where('y.id >= round(rand() * :randNum + :minId)')
                ->setParameters([
                    'randNum' => bcsub($maxId, $minId),
                    'minId'   => $minId
                ])
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (null === $randItem) continue;

            $id = $randItem->getId();
            if (in_array($id, $results)) continue;

            array_push($results, $id);
        }

        throw new Success(['data' => $results]);
    }

    /**
     * @Route("/info/{id}", methods={"GET"}, name="userInfo")
     * @param int $id
     */
    public function info(int $id)
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
     */
    public function getSelf(Token $token)
    {
        $user = $token->getCurrentUser();

        throw new Success(['data' => $this->serializer->normalize($user, 'json', $user->filterHidden())]);
    }

    /**
     * @Route("/", methods={"PATCH"}, name="updateUser")
     * @param Token   $token
     * @param Request $request
     */
    public function update(Token $token, Request $request)
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
     * Mail a registration code to an address.
     *
     * Unauthenticated - there is no account yet - so the address to use arrives in
     * the query string. Read from the query explicitly: App\Service\Request
     * ::getData() only looks at a JSON body or POST parameters, and folding query
     * parameters into it would break every validator that refuses extra fields.
     *
     * @Route("/register/code", methods={"GET"}, name="registerCode")
     * @param Request $request
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function registerCode(Request $request)
    {
        $data = ['email' => $request->getRequest()->query->get('email')];

        (new RegisterCode())->check($data);

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            throw new Used(['message' => '邮箱已被占用']);
        }

        $this->code->sendCode($this->code::REGISTER, $data['email'], $data['email']);

        throw new Success(['message' => '发送成功']);
    }

    /**
     * @Route("/password/code", methods={"GET"}, name="changePasswordCode")
     * @param Token $token
     * @throws InvalidArgumentException
     */
    public function changePasswordCode(Token $token)
    {
        $uid  = $token->getCurrentTokenKey('id');
        $user = $this->userRepository->find($uid);

        // The lookup used to live in the notification handler, which returned
        // quietly when it found nothing: the caller was told 发送成功 and no mail
        // was sent. Here a missing account is reported instead.
        if (empty($user)) throw new Miss(['message' => '用户不存在']);

        $this->code->sendCode($this->code::CHANGE_PASSWORD, (string) $uid, $user->getEmail(), $user->getName());

        throw new Success(['message' => '发送成功']);
    }

    /**
     * @Route("/password", methods={"PATCH"}, name="changePassword")
     * @param Token   $token
     * @param Request $request
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function password(Token $token, Request $request)
    {
        $uid  = $token->getCurrentTokenKey('id');
        $data = $request->getData();

        (new ChangePassword())->check($data);
        $this->code->checkCode($this->code::CHANGE_PASSWORD, (string) $uid, (int) $data['code']);

        $user = $this->userRepository->find($uid);

        $user->setPassword($data['password']);

        $this->entityManager->flush();

//        $token->cleanToken();

        throw new Success();
    }

    /**
     * @Route("/", methods={"DELETE"}, name="deleteUser")
     * @param Token $token
     * @throws InvalidArgumentException
     */
    public function disable(Token $token)
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
     * @throws Exception
     */
    public function setAvatarByUpload(Token $token, Request $request, UploadableManager $uploadableManager)
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
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
        throw new Success();
    }

    /**
     * @Route("/avatar/history", methods={"PUT"}, name="setAvatarByHistory", )
     * @param Token   $token
     * @param Request $request
     * @throws Exception
     */
    public function setAvatarByHistory(Token $token, Request $request)
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
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        throw new Success();
    }

    /**
     * @Route("/avatar/history", methods={"GET"}, name="getAvatarHistory")
     * @param Token $token
     */
    public function avatarHistory(Token $token)
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