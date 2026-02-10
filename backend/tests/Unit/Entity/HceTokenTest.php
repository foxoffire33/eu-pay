<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Card;
use App\Entity\HceToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV6;

class HceTokenTest extends TestCase
{
    public function testIdIsUuidV6(): void
    {
        $token = new HceToken();
        $this->assertInstanceOf(UuidV6::class, $token->getId());
    }

    public function testDefaultValues(): void
    {
        $token = new HceToken();
        $this->assertEquals(0, $token->getAtc());
        $this->assertEquals('VISA', $token->getCardScheme());
        $this->assertEquals('ACTIVE', $token->getStatus());
        $this->assertTrue($token->isActive());
    }

    public function testIsExpiredWithPastDate(): void
    {
        $token = new HceToken();
        $token->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->assertTrue($token->isExpired());
    }

    public function testIsNotExpiredWithFutureDate(): void
    {
        $token = new HceToken();
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->assertFalse($token->isExpired());
    }

    public function testIsActiveReturnsFalseWhenDeactivated(): void
    {
        $token = new HceToken();
        $token->setStatus('DEACTIVATED');
        $this->assertFalse($token->isActive());
    }

    public function testIsActiveReturnsFalseWhenSuspended(): void
    {
        $token = new HceToken();
        $token->setStatus('SUSPENDED');
        $this->assertFalse($token->isActive());
    }

    public function testAtcIncrement(): void
    {
        $token = new HceToken();
        $this->assertEquals(0, $token->getAtc());
        $token->setAtc($token->getAtc() + 1);
        $this->assertEquals(1, $token->getAtc());
        $token->setAtc($token->getAtc() + 1);
        $this->assertEquals(2, $token->getAtc());
    }

    public function testSettersAndGetters(): void
    {
        $user = new User();
        $card = new Card();
        $token = new HceToken();

        $token->setUser($user);
        $token->setCard($card);
        $token->setExternalCardId('card_xyz');
        $token->setDeviceFingerprint('abc123fingerprint');
        $token->setDpan('encrypted_dpan_data');
        $token->setSessionKey('encrypted_session_key');
        $token->setCardScheme('MASTERCARD');
        $token->setExpiryMonth(6);
        $token->setExpiryYear(2029);

        $this->assertSame($user, $token->getUser());
        $this->assertSame($card, $token->getCard());
        $this->assertEquals('card_xyz', $token->getExternalCardId());
        $this->assertEquals('abc123fingerprint', $token->getDeviceFingerprint());
        $this->assertEquals('encrypted_dpan_data', $token->getDpan());
        $this->assertEquals('encrypted_session_key', $token->getSessionKey());
        $this->assertEquals('MASTERCARD', $token->getCardScheme());
        $this->assertEquals(6, $token->getExpiryMonth());
        $this->assertEquals(2029, $token->getExpiryYear());
    }

    public function testCreatedAtIsSet(): void
    {
        $token = new HceToken();
        $this->assertInstanceOf(\DateTimeImmutable::class, $token->getCreatedAt());
    }

    public function testExpiresAtDefaultIsNearFuture(): void
    {
        $token = new HceToken();
        $this->assertGreaterThan(new \DateTimeImmutable(), $token->getExpiresAt());
    }
}
