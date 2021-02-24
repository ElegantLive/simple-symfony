<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/28
 * Time: 14:34
 */

namespace App\Controller;


use App\Entity\Base;
use App\Entity\Comment as CommentEntity;
use App\Entity\ThirdRelation;
use App\Entity\User;
use App\Exception\Forbidden;
use App\Exception\Gone;
use App\Exception\Miss;
use App\Exception\Parameter;
use App\Exception\Success;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use App\Repository\ThirdRelationRepository;
use App\Repository\UserRepository;
use App\Service\Request;
use App\Service\Serializer;
use App\Service\Token;
use App\Validator\Comment as CommentValidator;
use App\Validator\CommentPager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * @Route("/article/{id}/comment")
 * Class Comment
 * @package App\Controller
 */
class Comment extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var CommentRepository
     */
    private $commentRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var ArticleRepository
     */
    private $articleRepository;
    /**
     * @var Serializer
     */
    private $serializer;
    /**
     * @var ThirdRelationRepository
     */
    private $thirdRelationRepository;

    /**
     * Comment constructor.
     * @param EntityManagerInterface  $entityManager
     * @param CommentRepository       $commentRepository
     * @param UserRepository          $userRepository
     * @param ArticleRepository       $articleRepository
     * @param Serializer              $serializer
     * @param ThirdRelationRepository $thirdRelationRepository
     */
    public function __construct (EntityManagerInterface $entityManager,
                                 CommentRepository $commentRepository,
                                 UserRepository $userRepository,
                                 ArticleRepository $articleRepository,
                                 Serializer $serializer,
                                 ThirdRelationRepository $thirdRelationRepository)
    {
        $this->entityManager           = $entityManager;
        $this->commentRepository       = $commentRepository;
        $this->userRepository          = $userRepository;
        $this->articleRepository       = $articleRepository;
        $this->serializer              = $serializer;
        $this->thirdRelationRepository = $thirdRelationRepository;
    }

    /**
     * @Route("/", methods={"GET"}, name="getCommentsByArticle")
     * @param Request $request
     * @param Token   $token
     * @param int     $id
     * @param         $page
     * @param         $size
     * @param string  $order
     * @param string  $by
     * @throws Exception
     */
    public function getPager (Request $request,
                             Token $token,
                             int $id,
                             $page,
                             $size,
                             $order = Base::ORDER_DESC,
                             $by = CommentEntity::TIME)
    {
        $currentUser = null;
        $user = new User();
        try {
            $currentUser = $token->getCurrentUser();
        } catch (Exception $exception) {
        }

        $data = compact('page', 'size', 'order', 'by');

        $params = $request->request->query->all();
        foreach (array_keys($data) as $array_key) {
            if (array_key_exists($array_key, $params) === false) continue;
            if (in_array($array_key, ['page', 'size'])) $params[$array_key] = (int)$params[$array_key];

            $$array_key       = $params[$array_key];
            $data[$array_key] = $params[$array_key];
        }

        (new CommentPager())->check($data);

        $article = $this->articleRepository->find($id);
        if (empty($article)) throw new Miss();
        if ($article->isDeleted()) throw new Gone();

        $offset = ($page - 1) * $size;
        $max    = $size;

        $qb = $this->commentRepository->createQueryBuilder('t');

        $preQb = $qb->where($qb->expr()->isNull('t.deletedAt'))
            ->where('t.article = :article')
            ->setParameter('article', $article)
            ->orderBy('t.' . $by, $order);

        if ($by !== CommentEntity::TIME) $preQb->addOrderBy('t.' . CommentEntity::TIME, 'desc');

        $comments = $preQb->setFirstResult($offset)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();

        $allRecords = $preQb->getQuery()->getArrayResult();

        $total = count($allRecords);

        $pageTotal = ceil($total / $size);

        $list = [];

        array_map(function (CommentEntity $comment) use ($user, $currentUser, &$list) {
            $filter = $comment->isDeleted() ? $comment->getDeleteField() : $comment->getNormal();
            if ($comment->isDeleted() === false) {
                $filter['user'] = $user->getNormal();
            }

            $listItem = $this->serializer->normalize($comment, 'json', [AbstractNormalizer::ATTRIBUTES => $filter]);

            if ($currentUser) {
                $listItem['isLike']    = $this->thirdRelationRepository->suppleExist(ThirdRelation::COMMENT_LIKES, $currentUser->getId(), $comment->getId());
                $listItem['isDisLike'] = $this->thirdRelationRepository->suppleExist(ThirdRelation::COMMENT_LIKES, $currentUser->getId(), $comment->getId());
            }
            array_push($list, $listItem);
        }, $comments);

        throw new Success(['data' => compact('page', 'size', 'total', 'pageTotal', 'list')]);
    }

    /**
     * @Route("/", methods={"POST"}, name="newCommentForArticle")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @throws Exception
     */
    public function add (Token $token, Request $request, int $id)
    {
        $user = $token->getCurrentUser();

        if (empty($id)) throw new Parameter();

        $article = $this->articleRepository->find($id);
        if (empty($article)) throw new Miss();
        if ($article->isDeleted()) throw new Gone();

        $data = $request->getData();

        (new CommentValidator())->check($data);

        $comment = new CommentEntity();

        $comment->setContent($data['content']);
        $comment->setUser($user);
        $comment->setArticle($article);

        $this->entityManager->persist($comment);

        $article->setCommentCount(bcadd($article->getCommentCount(), 1));
        $this->entityManager->flush();

        throw new Success();
    }

    /**
     * @Route("/{commentId}", methods={"DELETE"}, name="deleteArticleComment")
     * @param Token $token
     * @param int   $articleId
     * @param int   $commentId
     */
    public function disable (Token $token, int $articleId, int $commentId)
    {
        $user = $token->getCurrentUser();

        $comment = $this->commentRepository->find($commentId);
        if (empty($comment)) throw new Miss();
        if ($comment->isDeleted()) throw new Gone();

        $article = $comment->getArticle();
        if (empty($article)) throw new Miss();
        if ($article->isDeleted()) throw new Gone();
        if ($article->getId() !== (int) $articleId) throw new Forbidden();

        $author = $article->getUser();

        $commenter = $comment->getUser();
        if ($commenter !== $user || $author !== $user) throw new Forbidden();

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        throw new Success();
    }
}