<?php

namespace App\Repository;

use App\Entity\ThirdRelation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method ThirdRelation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ThirdRelation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ThirdRelation[]    findAll()
 * @method ThirdRelation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ThirdRelationRepository extends ServiceEntityRepository
{
    public function __construct (ManagerRegistry $registry)
    {
        parent::__construct($registry, ThirdRelation::class);
    }

    // /**
    //  * @return ThirdRelation[] Returns an array of ThirdRelation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ThirdRelation
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function suppleTagsToArticle (TagRepository $repository, int $articleId)
    {
        $suppleTag = [];

        $map     = [
            'first'  => $articleId,
            'relate' => ThirdRelation::ARTICLE_TAGS
        ];
        $list = $this->findBy($map);

        $tagIds = [];
        foreach ($list as $item) {
            array_push($tagIds, $item->getSecond());
        }

        if ($tagIds) {
            $tagList = $repository->findBy(['id' => $tagIds]);

            foreach ($tagList as $tag) {
                $tagItem = [
                    'id'   => $tag->getId(),
                    'name' => $tag->getName()
                ];

                array_push($suppleTag, $tagItem);
            }
        }

        return $suppleTag;
    }

    /**
     * @param string $type
     * @param int    $first
     * @param int    $second
     * @return bool
     * @throws \Exception
     */
    public function suppleExist (string $type, int $first, int $second)
    {
        if (in_array($type, ThirdRelation::$types) === false) return false;
        if (empty($first)) return false;
        if (empty($second)) return false;

        $record = $this->findOneBy([
            'relate' => $type,
            'first'  => $first,
            'second' => $second
        ]);

        return $record ? true: false;
    }
}
