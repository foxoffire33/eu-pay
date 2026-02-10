<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WebAuthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebAuthnCredential>
 */
class WebAuthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebAuthnCredential::class);
    }

    public function findByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }

    /** @return WebAuthnCredential[] */
    public function findByUser(string $userId): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }
}
