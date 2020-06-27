<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/27
 * Time: 10:34
 */

namespace App\Controller;


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
     * @Route("/list/self", methods={"GET"}, name="getSelfArticleList")
     * @param Request $request
     * @param Token   $token
     * @param         $page
     * @param         $size
     * @param string  $order
     * @param string  $by
     * @throws \Exception
     */
    public function getSelfArticle (Request $request,
                                    Token $token,
                                    $page,
                                    $size,
                                    $order = Base::ORDER_DESC,
                                    $by = ArticleEntity::TIME)
    {
        $user = $token->getCurrentUser();

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
        $max    = $size;

        $qb = $this->articleRepository->createQueryBuilder('t');

        $preQb = $qb->where("t.user = :userId")
            ->setParameter('userId', $user->getId())
            ->orderBy('t.' . $by, $order);

        if ($by !== ArticleEntity::TIME) $preQb->addOrderBy('t.' . ArticleEntity::TIME, 'desc');

        $articles = $preQb->setFirstResult($offset)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();

        $count = $this->articleRepository->createQueryBuilder('t')
            ->where("t.user = :userId")
            ->setParameter('userId', $user->getId())
            ->select('count(1) as _c')
            ->getQuery()
            ->getArrayResult();

        $total = $count ? (int)$count[0]['_c']: 0;

        $pageTotal = ceil($total / $size);

        $list = [];

        array_map(function (ArticleEntity $article) use ($user, &$list) {
            $filter   = $article->isDeleted() ? $article->filterDeleted() : $article->filterHidden();
            $listItem = $this->serializer->normalize($article, 'json', $filter);

            if ($article->isDeleted() == false) {
                $listItem['tag'] = $this->thirdRelationRepository->suppleTagsToArticle($this->tagRepository, $article->getId());

                if ($user) {
                    $listItem['isLike']    = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_LIKES, $user->getId(), $article->getId());
                    $listItem['isDisLike'] = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_DISLIKES, $user->getId(), $article->getId());
                }
            }

            array_push($list, $listItem);
        }, $articles);

        throw new Success(['data' => compact('page', 'size', 'total', 'pageTotal', 'list')]);
    }

    /**
     * @Route("/list", methods={"GET"}, name="getArticleList")
     * @param Request $request
     * @param Token   $token
     * @param         $page
     * @param         $size
     * @param string  $order
     * @param string  $by
     * @throws \Exception
     */
    public function getPager (Request $request,
                                    Token $token,
                                    $page,
                                    $size,
                                    $order = Base::ORDER_DESC,
                                    $by = ArticleEntity::TIME)
    {
        $currentUser = null;
        try {
            $currentUser = $token->getCurrentUser();
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
        $max    = $size;

        $qb = $this->articleRepository->createQueryBuilder('t');

        $preQb = $qb->where($qb->expr()->isNull('t.deletedAt'))
            ->orderBy('t.' . $by, $order);

        if ($by !== ArticleEntity::TIME) $preQb->addOrderBy('t.' . ArticleEntity::TIME, 'desc');

        $articles = $preQb->setFirstResult($offset)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();

        $count = $this->articleRepository->createQueryBuilder('t')
            ->where("t.user = :userId")
            ->setParameter('userId', $currentUser->getId())
            ->select('count(1) as _c')
            ->getQuery()
            ->getArrayResult();

        $total = $count ? (int)$count[0]['_c']: 0;

        $pageTotal = ceil($total / $size);

        $list = [];

        array_map(function (ArticleEntity $article) use ($currentUser, &$list) {
            $listItem = $this->serializer->normalize($article, 'json', $article->filterHidden());

            $listItem['tag'] = $this->thirdRelationRepository->suppleTagsToArticle($this->tagRepository, $article->getId());

            if ($currentUser) {
                $listItem['isLike']    = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_LIKES, $currentUser->getId(), $article->getId());
                $listItem['isDisLike'] = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_DISLIKES, $currentUser->getId(), $article->getId());
            }
            array_push($list, $listItem);
        }, $articles);

        throw new Success(['data' => compact('page', 'size', 'total', 'pageTotal', 'list')]);
    }

    /**
     * @Route("/{id}", methods={"GET"}, name="articleDetail")
     * @param Token $token
     * @param int   $id
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
        if ($article->isDeleted()) throw new Gone([
            'data' => $this->serializer->normalize($article, 'json', $article->filterDeleted()),
            'message' => '文章已被撤下'
        ]);

        $articleData = $this->serializer->normalize($article, 'json', $article->filterHidden());

        $articleData['tag'] = $this->thirdRelationRepository->suppleTagsToArticle($this->tagRepository, $article->getId());

        if ($user) {
            $articleData['isLike']    = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_LIKES, $user->getId(), $article->getId());
            $articleData['isDisLike'] = $this->thirdRelationRepository->suppleExist(ThirdRelation::ARTICLE_DISLIKES, $user->getId(), $article->getId());
        }

        $article->setCommentCount(bcadd($article->getCommentCount(), 1));
        $this->entityManager->flush();

        throw new Success(['data' => $articleData]);
    }

    /**
     * @Route("/", methods={"POST"}, name="createArticle")
     * @param Token   $token
     * @param Request $request
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
                    $tagItem = $this->tagRepository->findOneBy(['name' => $item]);
                    if (empty($tagItem)) {
                        $tagItem = new Tag();

                        $tagItem->setName($item);
                        $tagItem->setDescription($item);

                        $this->entityManager->persist($tagItem);
                        $this->entityManager->flush();
                    }
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

            $tags = $this->tagRepository->findBy(['id' => $checkArray]);
            foreach ($tags as $tag) {
                $tag->setUseCount(bcadd($tag->getUseCount(), 1));
            }
            $this->entityManager->flush();

            $this->entityManager->commit();
        } catch (\Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        throw new Success();
    }

    /**
     * @Route("/{id}", methods={"PUT"}, name="updateArticle")
     * @param Token   $token
     * @param Request $request
     * @param int     $id
     * @throws \Exception
     */
    public function update (Token $token, Request $request, int $id)
    {
        $user = $token->getCurrentUser();

        $data = $request->getData();

        (new CreateArticle())->check($data);

        $article = $this->articleRepository->find($id);
        if (empty($article)) throw new Miss();
        if ($article->isDeleted()) throw new Gone();

        $author = $article->getUser();
        if ($user !== $author) throw new Forbidden();

        $checkIdArray  = [];
        $stringArray = [];
        if ($data['tag']) {
            foreach ($data['tag'] as $tag) {
                if (is_int($tag)) {
                    array_push($checkIdArray, $tag);
                } else {
                    array_push($stringArray, $tag);
                }
            }
            $checkIdArray = array_unique($checkIdArray);
            $checkIdArray = array_values($checkIdArray);

            $checkIds = implode(',', $checkIdArray);

            $tags = $this->tagRepository->findBy(['id' => $checkIds]);
            if (count($tags) !== count($checkIdArray)) throw new Parameter(['message' => '分类丢失']);
        }


        $this->entityManager->beginTransaction();
        try {
            $map = [
                'relate' => ThirdRelation::ARTICLE_TAGS,
                'first' => $article->getId()
            ];
            $oldTags = $this->thirdRelationRepository->findBy($map);

            foreach ($oldTags as $oldTag) {
                $backTag = $this->tagRepository->find($oldTag->getSecond());
                $backTag->setUseCount(bcsub($backTag->getUseCount(), 1));
                $this->entityManager->remove($oldTag);
            }

            if ($oldTags) $this->entityManager->flush();

            if ($stringArray) {
                // create tag
                foreach ($stringArray as $item) {
                    $tagItem = $this->tagRepository->findOneBy(['name' => $item]);
                    if (empty($tagItem)) {
                        $tagItem = new Tag();

                        $tagItem->setName($item);
                        $tagItem->setDescription($item);

                        $this->entityManager->persist($tagItem);
                        $this->entityManager->flush();
                    }
                    if (in_array($tagItem->getId(), $checkIdArray) === false) array_push($checkIdArray, $tagItem->getId());
                    $this->entityManager->clear(Tag::class);
                }
            }

            $article->setTrustFields($data);
            if (empty($article->getDescription())) {
                $description = substr($article->getContent(), 0, 25) . '...';
                $article->setDescription($description);
            }

            $this->entityManager->flush();

            foreach ($checkIdArray as $tagId) {
                $articleTag = new ThirdRelation();

                $articleTag->setRelate($articleTag::ARTICLE_TAGS);
                $articleTag->setFirst($article->getId());
                $articleTag->setSecond($tagId);

                $this->entityManager->persist($articleTag);
                $this->entityManager->flush();
                $this->entityManager->clear(ThirdRelation::class);
            }

            $tags = $this->tagRepository->findBy(['id' => $checkIdArray]);
            foreach ($tags as $tag) {
                $tag->setUseCount(bcadd($tag->getUseCount(), 1));
            }
            $this->entityManager->flush();

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