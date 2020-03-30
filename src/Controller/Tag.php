<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/30
 * Time: 14:34
 */

namespace App\Controller;


use App\Entity\Tag as TagEntity;
use App\Exception\Success;
use App\Repository\TagRepository;
use App\Service\Request;
use App\Service\Serializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * @Route("/tag")
 * Class Tag
 * @package App\Controller
 */
class Tag extends AbstractController
{
    /**
     * @var TagRepository
     */
    private $repository;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * Tag constructor.
     * @param TagRepository          $repository
     * @param EntityManagerInterface $entityManager
     * @param Serializer             $serializer
     */
    public function __construct (TagRepository $repository, EntityManagerInterface $entityManager, Serializer $serializer)
    {
        $this->repository    = $repository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/hot", methods={"GET"}, name="getHotTags")
     */
    public function getHot ()
    {
        $qb = $this->repository->createQueryBuilder('t');

        $results = $qb->where($qb->expr()->andX(
            $qb->expr()->isNull('t.deletedAt'),
            $qb->expr()->eq('t.isHot', ':hot')
        ))
            ->setParameter('hot', false)
            ->addOrderBy('t.useCount', 'desc')
            ->addOrderBy('t.createdAt', 'desc')
            ->setFirstResult(0)
            ->setMaxResults(9)
            ->getQuery()
            ->getResult();

        $list = [];

        array_map(function (TagEntity $tag) use (&$list) {
            $listItem = $this->serializer->normalize($tag, 'json', [AbstractNormalizer::ATTRIBUTES => $tag->getNormal()]);

            array_push($list, $listItem);
        }, $results);

        throw new Success(['data' => $list]);
    }

    /**
     * @Route("/search/{key}", methods={"GET"}, name="searchTagByKey")
     * @param Request $request
     * @param string  $key
     * @param int     $page
     * @param int     $size
     */
    public function searchTagByName (Request $request, string $key, int $page, int $size)
    {
        $params = $request->request->query->all();

        if (array_key_exists('page', $params)) $page = intval($params['page']) ?: $page;
        if (array_key_exists('size', $params)) $size = intval($params['size']) ?: $size;

        $offset = ($page - 1) * $size;
        $max    = $size;

        $qb = $this->repository->createQueryBuilder('t');

        $where = $qb->expr()->andX(
            $qb->expr()->isNull('t.deletedAt'),
            $qb->expr()->like('t.name', ":key")
        );

        $results = $qb->where($where)
            ->setParameter('key', "%$key%")
            ->addOrderBy('t.useCount', 'desc')
            ->addOrderBy('t.createdAt', 'desc')
            ->setFirstResult($offset)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();

        $count = $this->repository->createQueryBuilder('t')
            ->where($where)
            ->setParameter('key', "%$key%")
            ->addOrderBy('t.useCount', 'desc')
            ->addOrderBy('t.createdAt', 'desc')
            ->select("count(t.name) as _c")
            ->getQuery()
            ->getArrayResult();

        $total = $count ? (int)$count[0]['_c']: 0;

        $pageTotal = ceil($total / $size);

        $list = [];

        array_map(function (TagEntity $tag) use (&$list) {
            $listItem = $this->serializer->normalize($tag, 'json', [AbstractNormalizer::ATTRIBUTES => $tag->getNormal()]);

            array_push($list, $listItem);
        }, $results);

        throw new Success(['data' => compact('page', 'size', 'total', 'pageTotal', 'list')]);
    }
}