<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Card;
use App\Entity\HceToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HceToken>
 */
class HceTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HceToken::class);
    }

    public function findActiveForDevice(User $user, Card $card, string $deviceFingerprint): ?HceToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.card = :card')
            ->andWhere('t.deviceFingerprint = :fp')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('card', $card)
            ->setParameter('fp', $deviceFingerprint)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return HceToken[] */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getResult();
    }

    public function deactivateAllForCard(string $externalCardId): int
    {
        return $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':newStatus')
            ->where('t.externalCardId = :cardId')
            ->andWhere('t.status = :active')
            ->setParameter('newStatus', 'DEACTIVATED')
            ->setParameter('cardId', $externalCardId)
            ->setParameter('active', 'ACTIVE')
            ->getQuery()
            ->execute();
    }
}
