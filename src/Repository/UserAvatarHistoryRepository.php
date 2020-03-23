<?php

namespace App\Repository;

use App\Entity\UserAvatarHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method UserAvatarHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserAvatarHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserAvatarHistory[]    findAll()
 * @method UserAvatarHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserAvatarHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAvatarHistory::class);
    }

    // /**
    //  * @return UserAvatarHistory[] Returns an array of UserAvatarHistory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UserAvatarHistory
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
