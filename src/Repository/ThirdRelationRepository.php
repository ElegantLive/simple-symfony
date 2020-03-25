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
    public function __construct(ManagerRegistry $registry)
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
}
