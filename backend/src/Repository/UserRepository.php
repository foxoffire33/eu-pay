<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /** Find user by email blind index — used for login */
    public function findByEmailIndex(string $emailIndex): ?User
    {
        return $this->findOneBy(['emailIndex' => $emailIndex]);
    }

    public function findByPSD2 bankPersonId(string $personId): ?User
    {
        return $this->findOneBy(['externalPersonId' => $personId]);
    }
}
