<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\P2PTransfer;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class P2PTransferTest extends TestCase
{
    public function testNewTransferDefaults(): void
    {
        $transfer = new P2PTransfer();

        $this->assertNotNull($transfer->getId());
        $this->assertSame(P2PTransfer::STATUS_INITIATED, $transfer->getStatus());
        $this->assertSame('EUR', $transfer->getCurrency());
        $this->assertStringStartsWith('EUPAY-P2P-', $transfer->getReference());
        $this->assertNotNull($transfer->getCreatedAt());
        $this->assertNull($transfer->getCompletedAt());
        $this->assertNull($transfer->getRecipient());
    }

    public function testInternalTransfer(): void
    {
        $transfer = new P2PTransfer();
        $transfer->setType(P2PTransfer::TYPE_INTERNAL);
        $transfer->setAmountCents(5000);

        $this->assertTrue($transfer->isInternal());
        $this->assertFalse($transfer->isExternal());
        $this->assertSame('50.00', $transfer->getAmountEur());
    }

    public function testExternalTransfer(): void
    {
        $transfer = new P2PTransfer();
        $transfer->setType(P2PTransfer::TYPE_EXTERNAL);
        $transfer->setRecipientBic('RABONL2U');
        $transfer->setEncryptedRecipientIban('encrypted_blob');
        $transfer->setEncryptedRecipientName('encrypted_name');
        $transfer->setRecipientIbanIndex('hmac_index_hash');

        $this->assertTrue($transfer->isExternal());
        $this->assertFalse($transfer->isInternal());
        $this->assertSame('RABONL2U', $transfer->getRecipientBic());
        $this->assertSame('encrypted_blob', $transfer->getEncryptedRecipientIban());
        $this->assertSame('hmac_index_hash', $transfer->getRecipientIbanIndex());
    }

    public function testCompletedTransition(): void
    {
        $transfer = new P2PTransfer();
        $transfer->markCompleted();

        $this->assertSame(P2PTransfer::STATUS_COMPLETED, $transfer->getStatus());
        $this->assertNotNull($transfer->getCompletedAt());
    }

    public function testEncryptedMessage(): void
    {
        $transfer = new P2PTransfer();
        $transfer->setEncryptedMessage('rsa_encrypted_message_blob');

        $this->assertSame('rsa_encrypted_message_blob', $transfer->getEncryptedMessage());
    }
}
