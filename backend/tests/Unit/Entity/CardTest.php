<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Card;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV6;

class CardTest extends TestCase
{
    public function testIdIsUuidV6(): void
    {
        $card = new Card();
        $this->assertInstanceOf(UuidV6::class, $card->getId());
    }

    public function testDefaultValues(): void
    {
        $card = new Card();
        $this->assertEquals('VIRTUAL', $card->getType());
        $this->assertEquals('PROCESSING', $card->getStatus());
        $this->assertEquals('VISA', $card->getScheme());
        $this->assertNull($card->getLastFourDigits());
        $this->assertNull($card->getExpiryDate());
        $this->assertCount(0, $card->getHceTokens());
        $this->assertFalse($card->isActive());
    }

    public function testIsActiveWhenStatusActive(): void
    {
        $card = new Card();
        $card->setStatus('ACTIVE');
        $this->assertTrue($card->isActive());
    }

    public function testIsNotActiveForOtherStatuses(): void
    {
        $card = new Card();
        foreach (['PROCESSING', 'INACTIVE', 'BLOCKED', 'CLOSED'] as $status) {
            $card->setStatus($status);
            $this->assertFalse($card->isActive(), "Should not be active with status {$status}");
        }
    }

    public function testSettersAndGetters(): void
    {
        $user = new User();
        $card = new Card();
        $card->setUser($user);
        $card->setExternalCardId('card_abc123');
        $card->setExternalAccountId('acc_def456');
        $card->setType('PHYSICAL');
        $card->setScheme('MASTERCARD');
        $card->setLastFourDigits('4242');
        $card->setExpiryDate('12/2028');

        $this->assertSame($user, $card->getUser());
        $this->assertEquals('card_abc123', $card->getExternalCardId());
        $this->assertEquals('acc_def456', $card->getExternalAccountId());
        $this->assertEquals('PHYSICAL', $card->getType());
        $this->assertEquals('MASTERCARD', $card->getScheme());
        $this->assertEquals('4242', $card->getLastFourDigits());
        $this->assertEquals('12/2028', $card->getExpiryDate());
    }

    public function testCreatedAtIsSet(): void
    {
        $card = new Card();
        $this->assertInstanceOf(\DateTimeImmutable::class, $card->getCreatedAt());
    }
}
