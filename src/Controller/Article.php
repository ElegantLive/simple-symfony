<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/27
 * Time: 10:34
 */

namespace App\Controller;


use App\Controller\Traits\QueryCover;
use App\Entity\Article as ArticleEntity;
use App\Entity\Base;
use App\Entity\Tag;
use App\Entity\ThirdRelation;
use App\Exception\Forbidden;
use App\Exception\Gone;
use App\Exception\Miss;
use App\Exception\Parameter;
use App\Exception\Success;
use App\Repository\ArticleRepository;
use App\Repository\TagRepository;
use App\Repository\ThirdRelationRepository;
use App\Repository\UserRepository;
use App\Service\Request;
use App\Service\Serializer;
use App\Service\Token;
use App\Validator\CreateArticle;
use App\Validator\GetArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/article")
 * Class Article
 * @package App\Controller
 */
class Article extends AbstractController
{
    use QueryCover;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var ArticleRepository
     */
    private $articleRepository;
    /**
     * @var TagRepository
     */
    private $tagRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var Serializer
     */
    private $serializer;
    /**
     * @var ThirdRelationRepository
     */
    private $thirdRelationRepository;

    public function __construct (EntityManagerInterface $entityManager,
                                 ArticleRepository $articleRepository,
                                 TagRepository $tagRepository,
                                 UserRepository $userRepository,
                                 Serializer $serializer,
                                 ThirdRelationRepository $thirdRelationRepository)
    {
        $this->entityManager           = $entityManager;
        $this->articleRepository       = $articleRepository;
        $this->tagRepository           = $tagRepository;
        $this->userRepository          = $userRepository;
        $this->serializer              = $serializer;
        $this->thirdRelationRepository = $thirdRelationRepository;
    }

    /**
     * @Route("/{id}", methods={"GET"}, name="articleDetail")
     * @param Token $token
     * @param int   $id
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function detail (Token $token, int $id)
    {
        if (empty($id)) throw new Parameter(['message' => "文章id丢失"]);

        $user = null;
        try {
            $userId = $token->getCurrentTokenKey('id');

            $user = $this->userRepository->find($userId);
        } catch (\Exception $exception) {
        }

        $article = $this->articleRepository->find($id);
        if (empty($article)) throw new Miss(['message' => '文章不存在']);
        if ($article->isDeleted()) throw new Success(['data' => $this->serializer->normalize($article, 'json', $article->filterDeleted())]);

        $articleData = $this->serializer->normalize($article, 'json', $article->filterHidden());

        $articleData['tag'] = $this->thirdRelationRepository->suppleTagsToArticle($this->tagRepository, $article->getId());

        if ($user) {
            $articleData['isLike']    = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_LIKES, $article->getId(), $user->getId());
            $articleData['isDisLike'] = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_DISLIKES, $article->getId(), $user->getId());
        }

        throw new Success(['data' => $articleData]);
    }

    /**
     * @Route("/list", methods={"GET"}, name="getArticleList")
     * @param Request $request
     * @param Token   $token
     * @param         $page
     * @param         $size
     * @param string  $order
     * @param string  $by
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function getArticleList (Request $request,
                                    Token $token,
                                    $page,
                                    $size,
                                    $order = Base::ORDER_DESC,
                                    $by = ArticleEntity::TIME)
    {
        $user = null;
        try {
            $id = $token->getCurrentTokenKey('id');

            $user = $this->userRepository->find($id);
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

        (new GetArticle())->check($data);

        $offset = ($page - 1) * $size;
        $max = $offset + $size;

        $qb = $this->articleRepository->createQueryBuilder('t');

        $preQb = $qb->where($qb->expr()->isNull('t.deletedAt'))
            ->orderBy('t.' . $by, $order);

        $articles = $preQb->setFirstResult($offset)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();

        $allRecords = $preQb->getQuery()->getArrayResult();

        $total = count($allRecords);

        $pageTotal = ceil($total / $size);

        $list = [];
        foreach ($articles as $article) {
            $listItem = $this->serializer->normalize($article, 'json', $article->filterHidden());

            $listItem['tag'] = $this->thirdRelationRepository->suppleTagsToArticle($this->tagRepository, $article->getId());

            if ($user) {
                $listItem['isLike']    = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_LIKES, $article->getId(), $user->getId());
                $listItem['isDisLike'] = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_DISLIKES, $article->getId(), $user->getId());
            }
            array_push($list, $listItem);
        }

        throw new Success(['data' => compact('page', 'size', 'total', 'pageTotal', 'list')]);
    }

    /**
     * @Route("/", methods={"POST"}, name="createArticle")
     * @param Token   $token
     * @param Request $request
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function create (Token $token, Request $request)
    {
        $id = $token->getCurrentTokenKey('id');

        $data = $request->getData();

        (new CreateArticle())->check($data);

        $user = $this->userRepository->find($id);
        if (empty($user)) throw new Miss(['message' => '找不到发布用户']);
        if ($user->isDeleted()) throw new Gone(['message' => '用户已注销']);

        $checkArray  = [];
        $createArray = [];
        if ($data['tag']) {
            foreach ($data['tag'] as $tag) {
                if (is_int($tag)) {
                    array_push($checkArray, $tag);
                } else {
                    array_push($createArray, $tag);
                }
            }
            $checkArray = array_unique($checkArray);
            $checkArray = array_values($checkArray);

            $checkIds = implode(',', $checkArray);

            $tags = $this->tagRepository->findBy(['id' => $checkIds]);
            if (count($tags) !== count($checkArray)) throw new Parameter(['message' => '分类丢失']);
        }

        $this->entityManager->beginTransaction();
        try {
            if ($createArray) {
                // create tag
                foreach ($createArray as $item) {
                    $tagItem = new Tag();

                    $tagItem->setName($item);
                    $tagItem->setDescription($item);

                    $this->entityManager->persist($tagItem);
                    $this->entityManager->flush();
                    array_push($checkArray, $tagItem->getId());
                    $this->entityManager->clear(Tag::class);
                }
            }

            $article = new ArticleEntity();
            $article->setTrustFields($data);
            if (empty($article->getDescription())) {
                $description = substr($article->getContent(), 0, 25) . '...';
                $article->setDescription($description);
            }
            $article->setUser($user);

            $this->entityManager->persist($article);
            $this->entityManager->flush();

            foreach ($checkArray as $tagId) {
                $articleTag = new ThirdRelation();

                $articleTag->setRelate($articleTag::ARTICLE_TAGS);
                $articleTag->setFirst($article->getId());
                $articleTag->setSecond($tagId);

                $this->entityManager->persist($articleTag);
                $this->entityManager->flush();
                $this->entityManager->clear(ThirdRelation::class);
            }

            $this->entityManager->commit();
        } catch (\Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        throw new Success();
    }

    /**
     * @Route("/{id}", methods={"DELETE"}, name="deleteArticle")
     * @param Token $token
     * @param int   $id
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function delete (Token $token, int $id)
    {
        if (empty($id)) throw new Parameter(['message' => "文章id丢失"]);

        $userId = $token->getCurrentTokenKey('id');

        $user = $this->userRepository->find($userId);
        if (empty($user)) throw new Miss();
        if ($user->isDeleted()) throw new Gone();

        $article = $this->articleRepository->find($id);
        if (empty($article)) throw new Miss();
        if ($article->isDeleted()) throw new Gone();
        if ($article->getUser()->getId() !== $user->getId()) throw new Forbidden();

        $this->entityManager->remove($article);
        $this->entityManager->flush();

        throw new Success();
    }
}