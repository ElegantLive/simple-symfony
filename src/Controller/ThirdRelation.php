<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/28
 * Time: 11:45
 */

namespace App\Controller;


use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Reply;
use App\Entity\User;
use App\Exception\Done;
use App\Exception\Gone;
use App\Exception\Miss;
use App\Exception\Parameter;
use App\Exception\Success;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use App\Repository\ReplyRepository;
use App\Repository\ThirdRelationRepository;
use App\Repository\UserRepository;
use App\Service\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\Token;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\ThirdRelation as ThirdRelationEntity;

/**
 * Class ThirdRelation
 * @package App\Controller
 */
class ThirdRelation extends AbstractController
{
    protected static $typeRelate = [
        ThirdRelationEntity::ARTICLE_LIKES    => [User::class, Article::class],
        ThirdRelationEntity::COMMENT_LIKES    => [User::class, Comment::class],
        ThirdRelationEntity::REPLY_LIKES      => [User::class, Reply::class],
        ThirdRelationEntity::ARTICLE_DISLIKES => [User::class, Article::class],
        ThirdRelationEntity::COMMENT_DISLIKES => [User::class, Comment::class],
        ThirdRelationEntity::REPLY_DISLIKES   => [User::class, Reply::class],
    ];

    /**
     * @var ThirdRelationRepository
     */
    private $thirdRelationRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var ArticleRepository
     */
    private $articleRepository;
    /**
     * @var CommentRepository
     */
    private $commentRepository;
    /**
     * @var ReplyRepository
     */
    private $replyRepository;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * ThirdRelation constructor.
     * @param ThirdRelationRepository $thirdRelationRepository
     * @param UserRepository          $userRepository
     * @param ArticleRepository       $articleRepository
     * @param CommentRepository       $commentRepository
     * @param ReplyRepository         $replyRepository
     * @param EntityManagerInterface  $entityManager
     */
    public function __construct (ThirdRelationRepository $thirdRelationRepository,
                                 UserRepository $userRepository,
                                 ArticleRepository $articleRepository,
                                 CommentRepository $commentRepository,
                                 ReplyRepository $replyRepository,
                                 EntityManagerInterface $entityManager)
    {
        $this->thirdRelationRepository = $thirdRelationRepository;
        $this->userRepository          = $userRepository;
        $this->articleRepository       = $articleRepository;
        $this->commentRepository       = $commentRepository;
        $this->replyRepository         = $replyRepository;
        $this->entityManager           = $entityManager;
    }

    /**
     * @param string $className
     * @param int    $id
     * @return null|object
     * @throws \Exception
     */
    private function check (string $className, int $id)
    {
        if (empty($className) || empty($id)) throw new \Exception('miss check params');

        $object = $this->entityManager->getRepository($className)->find($id);

        if (empty($object)) throw new Miss(['message' => $className . $id]);
        if (property_exists($object, 'deletedAt')) {
            if ($object->isDeleted()) throw new Gone();
        }

        return $object;
    }

    /**
     * @param string $type
     * @return mixed
     */
    private function getRelateByType (string $type)
    {
        if (array_key_exists($type, self::$typeRelate)) return self::$typeRelate[$type];

        throw new Parameter(['message' => "关系字段错误"]);
    }

    /**
     * @param int         $first
     * @param int         $second
     * @param string      $type
     * @param string|null $clearType
     * @return array
     * @throws \Exception
     */
    private function addThird (int $first, int $second, string $type, string $clearType = null)
    {
        list($firstClass, $secondClass) = $this->getRelateByType($type);

        $first  = $this->check($firstClass, $first);
        $second = $this->check($secondClass, $second);

        $existMap = [
            'relate' => $type,
            'first'  => $first->getId(),
            'second' => $second->getId(),
        ];

        $exist = $this->thirdRelationRepository->findOneBy($existMap);
        if (isset($exist)) throw new Done();

        $thirdRelation = new ThirdRelationEntity();

        $thirdRelation->setRelate($type);
        $thirdRelation->setFirst($first->getId());
        $thirdRelation->setSecond($second->getId());

        $this->entityManager->persist($thirdRelation);

        $clean = false;
        if ($clearType) {
            if (array_key_exists($clearType, self::$typeRelate) === false) throw new Parameter(['message' => "关系字段错误"]);
            $existMap['relate'] = $clearType;

            $clearThird = $this->thirdRelationRepository->findOneBy($existMap);
            if ($clearThird) {
                $this->entityManager->remove($clearThird);
                $clean = true;
            }
        }

        $this->entityManager->flush();

        return [$first, $second, $clean];
    }

    /**
     * @param        $obj
     * @param string $type
     * @param array  $mapping
     * @param bool   $add
     * @param bool   $clean
     * @param array  $cleanMapping
     */
    public function afterCount ($obj, string $type, array $mapping, bool $add = true, bool $clean = false, array $cleanMapping = [])
    {
        $sMethods = sprintf('set%s', $mapping[$type]);
        $gMethods = sprintf('get%s', $mapping[$type]);

        $this->entityManager->persist($obj);

        $value = $add ? bcadd($obj->$gMethods(), 1): bcsub($obj->$gMethods(), 1);
        $obj->$sMethods($value);
        if ($clean) {
            $sMethods = sprintf('set%s', $cleanMapping[$type]);
            $gMethods = sprintf('get%s', $cleanMapping[$type]);

            $value = $add ? bcsub($obj->$gMethods(), 1): bcadd($obj->$gMethods(), 1);
            $obj->$sMethods($value);
        }

        $this->entityManager->flush();
    }


    /**
     * @param int    $first
     * @param int    $second
     * @param string $type
     * @return array
     * @throws \Exception
     */
    public function cancelThird (int $first, int $second, string $type)
    {
        list($firstClass, $secondClass) = $this->getRelateByType($type);

        $first  = $this->check($firstClass, $first);
        $second = $this->check($secondClass, $second);

        $existMap = [
            'relate' => $type,
            'first'  => $first->getId(),
            'second' => $second->getId(),
        ];

        $clearThird = $this->thirdRelationRepository->findOneBy($existMap);
        if (empty($clearThird)) throw new Done();

        $this->entityManager->remove($clearThird);
        $this->entityManager->flush();

        return [$first, $second];
    }

    /**
     * @param Token  $token
     * @param int    $id
     * @param string $type
     * @param array  $toggleType
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function addToggleAction (Token $token, int $id, string $type, array $toggleType)
    {
        $userId = $token->getCurrentTokenKey('id');

        if (empty($type)) throw new Parameter(['message' => '请重试']);
        if (in_array($type, ['like', 'dislike']) == false) throw new Parameter(['参数类型错误']);

        $cleanType            = [];
        $cleanType['dislike'] = $toggleType['like'];
        $cleanType['like']    = $toggleType['dislike'];

        list(, $comment, $clean) = $this->addThird($userId, $id, $toggleType[$type], $cleanType[$type]);

        $mapping = ['like' => 'LikeCount', 'dislike' => 'DisLikeCount'];
        $cleanMapping = ['like' => $mapping['dislike'], 'dislike' => $mapping['like']];
        $this->afterCount($comment, $type, $mapping, true, $clean, $cleanMapping);
    }

    /**
     * @param Token  $token
     * @param int    $id
     * @param string $type
     * @param array  $toggleType
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function cancelToggleAction (Token $token, int $id, string $type, array $toggleType)
    {
        $userId = $token->getCurrentTokenKey('id');
        if (empty($type)) throw new Parameter(['message' => '请重试']);
        if (in_array($type, ['like', 'dislike']) == false) throw new Parameter(['参数类型错误']);

        list(, $reply) = $this->cancelThird($userId, $id, $toggleType[$type]);
        $mapping = ['like' => 'LikeCount', 'dislike' => 'DisLikeCount'];
        $this->afterCount($reply, $type, $mapping, false);
    }
    /**
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @param string  $type
     * @param bool    $add
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function toggles (Token $token, Request $request, int $id, string $type, bool $add = true)
    {
        $path = $request->request->getPathInfo();

        $obj = explode('/', $path)[1];

        $toggleType = [
            'comment' => [
                'like'    => ThirdRelationEntity::COMMENT_LIKES,
                'dislike' => ThirdRelationEntity::COMMENT_DISLIKES
            ],
            'article' => [
                'like'    => ThirdRelationEntity::ARTICLE_LIKES,
                'dislike' => ThirdRelationEntity::ARTICLE_DISLIKES
            ],
            'reply' => [
                'like'    => ThirdRelationEntity::REPLY_LIKES,
                'dislike' => ThirdRelationEntity::REPLY_DISLIKES
            ],
        ];

        if (array_key_exists($obj, $toggleType) === false) throw new Parameter(['message' => '参数错误']);

        if ($add) {
            $this->addToggleAction($token, $id, $type, $toggleType[$obj]);
        } else {
            $this->cancelToggleAction($token, $id, $type, $toggleType[$obj]);
        }
    }

    /**
     * @Route("/article/{id}/{type}", methods={"POST"}, name="addLikeForArticle")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @param string  $type
     * @throws \Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function addArticleToggle (Token $token, Request $request, int $id, string $type)
    {
        $this->toggles($token, $request, $id, $type);

        throw new Success();
    }

    /**
     * @Route("/article/{id}/{type}", methods={"DELETE"}, name="cancelLikeForArticle")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @param string  $type
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function cancelArticleToggle (Token $token, Request $request, int $id, string $type)
    {
        $this->toggles($token, $request, $id, $type, false);

        throw new Success();
    }

    /**
     * @Route("/comment/{id}/{type}", methods={"POST"}, name="addLikeForComment")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @param string  $type
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function addCommentToggle (Token $token, Request $request, int $id, string $type)
    {
        $this->toggles($token, $request, $id, $type);

        throw new Success();
    }

    /**
     * @Route("/comment/{id}/{type}", methods={"DELETE"}, name="cancelLikeForComment")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @param string  $type
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function cancelCommentToggle (Token $token, Request $request, int $id, string $type)
    {
        $this->toggles($token, $request, $id, $type, false);

        throw new Success();
    }

    /**
     * @Route("/reply/{id}/{type}", methods={"POST"}, name="addLikeForReply")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @param string  $type
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function addReplyToggle (Token $token, Request $request, int $id, string $type)
    {
        $this->toggles($token, $request, $id, $type);

        throw new Success();
    }

    /**
     * @Route("/reply/{id}/{type}", methods={"DELETE"}, name="cancelLikeForReply")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @param string  $type
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function cancelReplyToggle (Token $token, Request $request, int $id, string $type)
    {
        $this->toggles($token, $request, $id, $type, false);

        throw new Success();
    }
}