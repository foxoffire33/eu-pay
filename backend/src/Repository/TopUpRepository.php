<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TopUp;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TopUp>
 */
class TopUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TopUp::class);
    }

    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByExternalPaymentId(string $paymentId): ?TopUp
    {
        return $this->findOneBy(['externalPaymentId' => $paymentId]);
    }

    public function findPendingOlderThan(\DateTimeImmutable $cutoff): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status IN (:statuses)')
            ->andWhere('t.createdAt < :cutoff')
            ->setParameter('statuses', [TopUp::STATUS_INITIATED, TopUp::STATUS_PENDING])
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }
}
