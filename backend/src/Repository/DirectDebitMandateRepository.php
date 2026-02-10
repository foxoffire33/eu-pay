<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DirectDebitMandate;
use App\Entity\LinkedBankAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DirectDebitMandate>
 */
class DirectDebitMandateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DirectDebitMandate::class);
    }

    public function findActiveByUser(User $user): ?DirectDebitMandate
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('m.status = :active')
            ->setParameter('user', $user)
            ->setParameter('active', DirectDebitMandate::STATUS_ACTIVE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return DirectDebitMandate[] */
    public function findByLinkedAccount(LinkedBankAccount $account): array
    {
        return $this->findBy(['linkedBankAccount' => $account]);
    }

    public function findByMandateReference(string $ref): ?DirectDebitMandate
    {
        return $this->findOneBy(['mandateReference' => $ref]);
    }
}
