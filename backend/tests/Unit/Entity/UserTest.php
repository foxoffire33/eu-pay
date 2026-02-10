<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV6;

class UserTest extends TestCase
{
    public function testIdIsUuidV6(): void
    {
        $user = new User();
        $this->assertInstanceOf(UuidV6::class, $user->getId());
    }

    public function testUniqueIdsAreGenerated(): void
    {
        $a = new User();
        $b = new User();
        $this->assertNotEquals((string) $a->getId(), (string) $b->getId());
    }

    public function testUuidV6IsTimeOrdered(): void
    {
        $a = new User();
        usleep(1000);
        $b = new User();
        $this->assertLessThan((string) $b->getId(), (string) $a->getId());
    }

    public function testDefaultValues(): void
    {
        $user = new User();
        $this->assertEquals('PENDING', $user->getKycStatus());
        $this->assertNull($user->getExternalPersonId());
        $this->assertNull($user->getExternalAccountId());
        $this->assertNull($user->getEncryptedIban());
        $this->assertNull($user->getPublicKey());
        $this->assertFalse($user->hasPublicKey());
        $this->assertCount(0, $user->getCards());
        $this->assertCount(0, $user->getHceTokens());
    }

    public function testEncryptedFieldSettersAndGetters(): void
    {
        $user = new User();
        $user->setEncryptedEmail('encrypted_email_blob');
        $user->setEmailIndex('abc123def456');
        $user->setEncryptedFirstName('encrypted_first_name_blob');
        $user->setEncryptedLastName('encrypted_last_name_blob');
        $user->setEncryptedPhoneNumber('encrypted_phone_blob');
        $user->setEncryptedIban('encrypted_iban_blob');
        $user->setPassword('hashed');
        $user->setExternalPersonId('per_abc123');
        $user->setExternalAccountId('acc_def456');
        $user->setKycStatus('COMPLETED');

        $this->assertEquals('encrypted_email_blob', $user->getEncryptedEmail());
        $this->assertEquals('abc123def456', $user->getEmailIndex());
        // UserIdentifier returns blind index (not email)
        $this->assertEquals('abc123def456', $user->getUserIdentifier());
        $this->assertEquals('encrypted_first_name_blob', $user->getEncryptedFirstName());
        $this->assertEquals('encrypted_last_name_blob', $user->getEncryptedLastName());
        $this->assertEquals('encrypted_phone_blob', $user->getEncryptedPhoneNumber());
        $this->assertEquals('encrypted_iban_blob', $user->getEncryptedIban());
        $this->assertEquals('hashed', $user->getPassword());
        $this->assertEquals('per_abc123', $user->getExternalPersonId());
        $this->assertEquals('acc_def456', $user->getExternalAccountId());
        $this->assertEquals('COMPLETED', $user->getKycStatus());
    }

    public function testPublicKeyManagement(): void
    {
        $user = new User();
        $this->assertFalse($user->hasPublicKey());

        $user->setPublicKey('MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...');
        $user->setPublicKeyFingerprint('abc123fingerprint');

        $this->assertTrue($user->hasPublicKey());
        $this->assertEquals('MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...', $user->getPublicKey());
        $this->assertEquals('abc123fingerprint', $user->getPublicKeyFingerprint());
    }

    public function testRolesAlwaysIncludeRoleUser(): void
    {
        $user = new User();
        $this->assertContains('ROLE_USER', $user->getRoles());

        $user->setRoles(['ROLE_ADMIN']);
        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testRolesAreDeduplicated(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);
        $roles = $user->getRoles();
        $this->assertEquals(array_unique($roles), $roles);
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();
        $user->eraseCredentials();
        $this->assertTrue(true);
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $user = new User();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $user->getCreatedAt());
        $this->assertLessThanOrEqual($after, $user->getCreatedAt());
    }
}
