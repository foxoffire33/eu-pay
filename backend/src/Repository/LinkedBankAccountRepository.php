<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LinkedBankAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LinkedBankAccount>
 */
class LinkedBankAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LinkedBankAccount::class);
    }

    /** @return LinkedBankAccount[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.status != :revoked')
            ->setParameter('user', $user)
            ->setParameter('revoked', LinkedBankAccount::STATUS_REVOKED)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return LinkedBankAccount[] */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.status = :active')
            ->setParameter('user', $user)
            ->setParameter('active', LinkedBankAccount::STATUS_ACTIVE)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByConsentId(string $consentId): ?LinkedBankAccount
    {
        return $this->findOneBy(['consentId' => $consentId]);
    }
}
