<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Transaction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV6;

class TransactionTest extends TestCase
{
    public function testIdIsUuidV6(): void
    {
        $tx = new Transaction();
        $this->assertInstanceOf(UuidV6::class, $tx->getId());
    }

    public function testDefaultValues(): void
    {
        $tx = new Transaction();
        $this->assertEquals('CARD_AUTHORIZATION', $tx->getType());
        $this->assertEquals('PENDING', $tx->getStatus());
        $this->assertEquals('0.00', $tx->getAmount());
        $this->assertEquals('EUR', $tx->getCurrency());
        $this->assertNull($tx->getEncryptedMerchantName());
        $this->assertNull($tx->getEncryptedMerchantCity());
        $this->assertNull($tx->getPosEntryMode());
    }

    public function testSettersAndGetters(): void
    {
        $tx = new Transaction();
        $tx->setExternalTransactionId('tx_abc123');
        $tx->setType('CARD_PAYMENT');
        $tx->setStatus('COMPLETED');
        $tx->setAmount('49.99');
        $tx->setCurrency('EUR');
        $tx->setEncryptedMerchantName('encrypted_merchant_blob');
        $tx->setEncryptedMerchantCity('encrypted_city_blob');
        $tx->setPosEntryMode('NFC');

        $this->assertEquals('tx_abc123', $tx->getExternalTransactionId());
        $this->assertEquals('CARD_PAYMENT', $tx->getType());
        $this->assertEquals('COMPLETED', $tx->getStatus());
        $this->assertEquals('49.99', $tx->getAmount());
        $this->assertEquals('EUR', $tx->getCurrency());
        $this->assertEquals('encrypted_merchant_blob', $tx->getEncryptedMerchantName());
        $this->assertEquals('encrypted_city_blob', $tx->getEncryptedMerchantCity());
        $this->assertEquals('NFC', $tx->getPosEntryMode());
    }

    public function testBackwardCompatGetMerchantName(): void
    {
        $tx = new Transaction();
        $tx->setEncryptedMerchantName('encrypted_blob');
        // getMerchantName() is the backward compat alias
        $this->assertEquals('encrypted_blob', $tx->getMerchantName());
    }

    public function testTimestamps(): void
    {
        $before = new \DateTimeImmutable();
        $tx = new Transaction();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $tx->getCreatedAt());
        $this->assertLessThanOrEqual($after, $tx->getCreatedAt());
    }
}
