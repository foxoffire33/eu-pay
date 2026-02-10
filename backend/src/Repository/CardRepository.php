<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Card;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    /** @return Card[] */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getResult();
    }

    public function findByExternalCardId(string $cardId): ?Card
    {
        return $this->findOneBy(['externalCardId' => $cardId]);
    }
}
