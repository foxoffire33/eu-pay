<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\P2PTransfer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<P2PTransfer>
 */
class P2PTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, P2PTransfer::class);
    }

    /** Transfers sent BY this user */
    public function findSentByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.sender = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Transfers received BY this user (internal only) */
    public function findReceivedByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Find internal recipient by email blind index */
    public function findByRecipientEmailIndex(string $emailIndex): ?P2PTransfer
    {
        return $this->findOneBy(['recipientEmailIndex' => $emailIndex]);
    }
}
