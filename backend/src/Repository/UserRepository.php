<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** Find user by email blind index — legacy lookup */
    public function findByEmailIndex(string $emailIndex): ?User
    {
        return $this->findOneBy(['emailIndex' => $emailIndex]);
    }

    public function findByPSD2BankPersonId(string $personId): ?User
    {
        return $this->findOneBy(['externalPersonId' => $personId]);
    }
}
