<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserGdprTest extends TestCase
{
    public function testConsentDefaultsFalse(): void
    {
        $user = new User();
        $this->assertFalse($user->isGdprConsentGiven());
        $this->assertFalse($user->isDeviceTrackingConsent());
        $this->assertFalse($user->isMarketingConsent());
        $this->assertFalse($user->isAnonymized());
    }

    public function testConsentSetters(): void
    {
        $user = new User();
        $user->setGdprConsentGiven(true);
        $user->setGdprConsentAt(new \DateTimeImmutable('2026-01-01'));
        $user->setPrivacyPolicyVersion('1.0.0');
        $user->setDeviceTrackingConsent(true);
        $user->setMarketingConsent(true);
        $user->setLegalBasis('contract');

        $this->assertTrue($user->isGdprConsentGiven());
        $this->assertEquals('2026-01-01', $user->getGdprConsentAt()->format('Y-m-d'));
        $this->assertEquals('1.0.0', $user->getPrivacyPolicyVersion());
        $this->assertTrue($user->isDeviceTrackingConsent());
        $this->assertTrue($user->isMarketingConsent());
        $this->assertEquals('contract', $user->getLegalBasis());
    }

    public function testAnonymizeClearsEncryptedPii(): void
    {
        $user = new User();
        $user->setEncryptedEmail('encrypted_email_blob');
        $user->setEmailIndex('hmac_index_abc123');
        $user->setEncryptedFirstName('encrypted_first');
        $user->setEncryptedLastName('encrypted_last');
        $user->setEncryptedPhoneNumber('encrypted_phone');
        $user->setEncryptedIban('encrypted_iban');
        $user->setPublicKey('RSA_PUBLIC_KEY_BASE64');
        $user->setPublicKeyFingerprint('sha256_fingerprint');
        $user->setPassword('$argon2id$hash');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setMarketingConsent(true);
        $user->setDeviceTrackingConsent(true);

        $user->anonymize();

        // Encrypted blobs are cleared
        $this->assertEmpty($user->getEncryptedEmail());
        $this->assertEmpty($user->getEncryptedFirstName());
        $this->assertEmpty($user->getEncryptedLastName());
        $this->assertNull($user->getEncryptedPhoneNumber());
        $this->assertNull($user->getEncryptedIban());

        // Public key destroyed — can never re-encrypt
        $this->assertNull($user->getPublicKey());
        $this->assertNull($user->getPublicKeyFingerprint());
        $this->assertFalse($user->hasPublicKey());

        // Auth cleared
        $this->assertEmpty($user->getPassword());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());

        // Blind index replaced with random (prevents lookup)
        $this->assertStringStartsWith('anon_', $user->getEmailIndex());

        // Consent revoked
        $this->assertFalse($user->isMarketingConsent());
        $this->assertFalse($user->isDeviceTrackingConsent());

        // Status flags set
        $this->assertTrue($user->isAnonymized());
        $this->assertNotNull($user->getAnonymizedAt());
    }

    public function testAnonymizeProducesUniqueBlindIndexes(): void
    {
        $a = new User();
        $a->setEncryptedEmail('enc_a'); $a->setEmailIndex('idx_a');
        $a->setEncryptedFirstName('f'); $a->setEncryptedLastName('l');
        $a->setPassword('p');

        $b = new User();
        $b->setEncryptedEmail('enc_b'); $b->setEmailIndex('idx_b');
        $b->setEncryptedFirstName('f'); $b->setEncryptedLastName('l');
        $b->setPassword('p');

        $a->anonymize();
        $b->anonymize();

        // Each anonymized user gets a unique blind index
        $this->assertNotEquals($a->getEmailIndex(), $b->getEmailIndex());
    }

    public function testAnonymizePreservesUuidAndTimestamps(): void
    {
        $user = new User();
        $user->setEncryptedEmail('enc'); $user->setEmailIndex('idx');
        $user->setEncryptedFirstName('f'); $user->setEncryptedLastName('l');
        $user->setPassword('p');

        $idBefore = (string) $user->getId();
        $createdBefore = $user->getCreatedAt();

        $user->anonymize();

        // UUID preserved (needed for AML audit trail)
        $this->assertEquals($idBefore, (string) $user->getId());
        // Timestamps preserved
        $this->assertEquals($createdBefore, $user->getCreatedAt());
    }

    public function testAnonymizedBlindIndexStartsWithAnon(): void
    {
        $user = new User();
        $user->setEncryptedEmail('enc'); $user->setEmailIndex('original');
        $user->setEncryptedFirstName('f'); $user->setEncryptedLastName('l');
        $user->setPassword('p');
        $user->anonymize();

        $this->assertStringStartsWith('anon_', $user->getEmailIndex());
        // anon_ + 32 hex chars
        $this->assertEquals(37, strlen($user->getEmailIndex()));
    }

    public function testDataRetention(): void
    {
        $user = new User();
        $fiveYears = new \DateTimeImmutable('+5 years');
        $user->setDataRetentionUntil($fiveYears);
        $this->assertEquals($fiveYears, $user->getDataRetentionUntil());
    }

    public function testLegalBasisDefault(): void
    {
        $user = new User();
        $this->assertEquals('contract', $user->getLegalBasis());
    }
}
