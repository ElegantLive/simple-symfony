<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/30
 * Time: 16:38
 */

namespace App\Controller;


use App\Entity\Base;
use App\Entity\Reply as ReplyEntity;
use App\Entity\ThirdRelation;
use App\Exception\Forbidden;
use App\Exception\Gone;
use App\Exception\Miss;
use App\Exception\Success;
use App\Repository\CommentRepository;
use App\Repository\ReplyRepository;
use App\Repository\ThirdRelationRepository;
use App\Service\Request;
use App\Service\Serializer;
use App\Service\Token;
use App\Validator\PostReply;
use App\Validator\ReplyPager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;


/**
 * @Route("/article/{articleId}/comment/{commentId}/reply")
 * Class Reply
 * @package App\Controller
 */
class Reply extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var ReplyRepository
     */
    private $replyRepository;
    /**
     * @var Serializer
     */
    private $serializer;
    /**
     * @var CommentRepository
     */
    private $commentRepository;
    /**
     * @var ThirdRelationRepository
     */
    private $thirdRelationRepository;

    /**
     * Reply constructor.
     * @param EntityManagerInterface  $entityManager
     * @param ReplyRepository         $replyRepository
     * @param Serializer              $serializer
     * @param CommentRepository       $commentRepository
     * @param ThirdRelationRepository $thirdRelationRepository
     */
    public function __construct (EntityManagerInterface $entityManager,
                                 ReplyRepository $replyRepository,
                                 Serializer $serializer,
                                 CommentRepository $commentRepository,
                                 ThirdRelationRepository $thirdRelationRepository)
    {
        $this->entityManager           = $entityManager;
        $this->replyRepository         = $replyRepository;
        $this->serializer              = $serializer;
        $this->commentRepository       = $commentRepository;
        $this->thirdRelationRepository = $thirdRelationRepository;
    }

    /**
     * @Route("/", methods={"GET"}, name="getCommentReplies")
     * @param Request $request
     * @param Token   $token
     * @param int     $articleId
     * @param int     $commentId
     * @param         $page
     * @param         $size
     * @param string  $order
     * @param string  $by
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function getPager (Request $request,
                              Token $token,
                              int $articleId,
                              int $commentId,
                              $page,
                              $size,
                              $order = Base::ORDER_DESC,
                              $by = ReplyEntity::TIME)
    {

        $user = null;
        try {
            $user = $token->getCurrentUser();
        } catch (\Exception $exception) {
        }

        $data = compact('page', 'size', 'order', 'by');

        $params = $request->request->query->all();
        foreach (array_keys($data) as $array_key) {
            if (array_key_exists($array_key, $params) === false) continue;
            if (in_array($array_key, ['page', 'size'])) $params[$array_key] = (int)$params[$array_key];

            $$array_key       = $params[$array_key];
            $data[$array_key] = $params[$array_key];
        }

        (new ReplyPager())->check($data);

        $list      = [];
        $total     = 0;
        $pageTotal = 0;

        $default = ['data' => compact('page', 'size', 'total', 'pageTotal', 'list')];

        $comment = $this->commentRepository->find($commentId);
        if (empty($comment)) throw new Miss($default);
        if ($comment->isDeleted()) throw new Gone($default);

        $article = $comment->getArticle();
        if (empty($article)) throw new Miss($default);
        if ($article->isDeleted()) throw new Gone($default);
        if ($article->getId() !== $articleId) throw new Forbidden($default);

        $offset = ($page - 1) * $size;
        $max    = $offset + $size;

        $qb = $this->replyRepository->createQueryBuilder('t');

        $where = $qb->expr()->andX(
            $qb->expr()->isNull('t.deletedAt'),
            $qb->expr()->eq('t.comment', ":comment")
        );

        $preQb = $qb->where($where)
            ->setParameter("comment", $comment)
            ->orderBy('t.' . $by, $order);

        if ($by !== ReplyEntity::TIME) $preQb->addOrderBy('t.' . ReplyEntity::TIME, 'desc');

        $replies = $preQb->setFirstResult($offset)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();

        $count = $this->replyRepository->createQueryBuilder('t')
            ->where($where)
            ->setParameter("comment", $comment)
            ->select("count(t.id) as _c")
            ->getQuery()
            ->getArrayResult();

        $total = $count ? (int)$count[0]['_c'] : 0;

        $pageTotal = ceil($total / $size);

        array_map(function (ReplyEntity $reply) use ($user, &$list) {
            $attributes = $reply->getNormal();
            $replier = $reply->getUser();
            $attributes['user'] = $replier->isDeleted() ? $replier->getDeleteField():$replier->getNormal();

            $listItem = $this->serializer->normalize($reply, 'json', [AbstractNormalizer::ATTRIBUTES => $attributes]);

            $replier = $reply->getReply();
            if ($replier) {
                $replier = $this->replyRepository->find($replier);
                if ($replier && $replier->isDeleted() == false) {
                    $replier = $replier->getUser();
                    if ($replier) {
                        $attributes = [AbstractNormalizer::ATTRIBUTES => $replier->isDeleted() ? $replier->getDeleteField(): $replier->getNormal()];
                        $listItem['replier'] = $this->serializer->normalize($replier, 'json', $attributes);
                    }
                }
            }

            if ($user) {
                $listItem['isLike']    = $this->thirdRelationRepository->suppleExist(ThirdRelation::REPLY_LIKES, $user->getId(), $reply->getId());
                $listItem['isDisLike'] = $this->thirdRelationRepository->suppleExist(ThirdRelation::REPLY_DISLIKES, $user->getId(), $reply->getId());
            }
            array_push($list, $listItem);
        }, $replies);

        throw new Success(['data' => compact('page', 'size', 'total', 'pageTotal', 'list')]);
    }
//
//    /**
//     * @Route("/", methods={"POST"}, name="replyArticleComment")
//     * @param Token   $token
//     * @param Request $request
//     * @param int     $articleId
//     * @param int     $commentId
//     * @throws \Psr\Cache\InvalidArgumentException
//     * @throws \Exception
//     */
//    public function replyComment (Token $token, Request $request, int $articleId, int $commentId)
//    {
//        $user = $token->getCurrentUser();
//
//        $data = $request->getData();
//
//        (new PostReply())->check($data);
//
//        $comment = $this->commentRepository->find($commentId);
//        if (empty($comment)) throw new Miss();
//        if ($comment->isDeleted()) throw new Gone();
//
//        $article = $comment->getArticle();
//        if (empty($article)) throw new Miss();
//        if ($article->isDeleted()) throw new Gone();
//        if ($articleId !== $article->getId()) throw new Forbidden();
//
//        $reply = new ReplyEntity();
//
//        $reply->setUser($user);
//        $reply->setContent($data['content']);
//        $reply->setComment($comment);
//
//        $this->entityManager->persist($reply);
//
//        $comment->setReplyCount(bcadd($comment->getReplyCount(), 1));
//        $this->entityManager->flush();
//
//        throw new Success();
//    }

    /**
     * @Route("/{replyId}", methods={"POST"}, name="replyArticleComment")
     * @param Token   $token
     * @param Request $request
     * @param int     $articleId
     * @param int     $commentId
     * @param int     $replyId
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function replyReply (Token $token, Request $request, int $articleId, int $commentId, int $replyId = 0)
    {
        $user = $token->getCurrentUser();

        $data = $request->getData();

        (new PostReply())->check($data);

        if ($replyId) {
            $reply = $this->replyRepository->find($replyId);
            if (empty($reply)) throw new Miss();
            if ($reply->isDeleted()) throw new Gone();
        }

        $comment = $replyId ? $reply->getComment(): $this->commentRepository->find($commentId);
        if (empty($comment)) throw new Miss();
        if ($comment->isDeleted()) throw new Gone();
        if ($comment->getId() !== $commentId) throw new Forbidden();

        $article = $comment->getArticle();
        if (empty($article)) throw new Miss();
        if ($article->isDeleted()) throw new Gone();
        if ($articleId !== $article->getId()) throw new Forbidden();

        $reply = new ReplyEntity();

        $reply->setUser($user);
        $reply->setContent($data['content']);
        $reply->setComment($comment);
        $reply->setReply($replyId);

        $this->entityManager->persist($reply);

        $comment->setReplyCount(bcadd($comment->getReplyCount(), 1));
        $this->entityManager->flush();

        throw new Success();
    }

    /**
     * @Route("/{replyId}", methods={"DELETE"}, name="deleteCommentReply")
     * @param Token $token
     * @param int   $articleId
     * @param int   $commentId
     * @param int   $replyId
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function disable (Token $token, int $articleId, int $commentId, int $replyId)
    {
        $user = $token->getCurrentUser();

        $reply = $this->replyRepository->find($replyId);
        if (empty($reply)) throw new Miss();
        if ($reply->isDeleted()) throw new Gone();

        $comment = $reply->getComment();
        if (empty($comment)) throw new Miss();
        if ($comment->isDeleted()) throw new Gone();
        if ($comment->getId() !== $commentId) throw new Forbidden();

        $article = $comment->getArticle();
        if (empty($article)) throw new Miss();
        if ($article->isDeleted()) throw new Gone();
        if ($articleId !== $article->getId()) throw new Forbidden();

        $replier = $reply->getUser();
        if ($replier !== $user) throw new Forbidden();

        $this->entityManager->remove($reply);
        $this->entityManager->flush();

        throw new Success();
    }
}