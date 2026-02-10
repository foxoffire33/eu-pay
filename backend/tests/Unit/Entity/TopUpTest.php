<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TopUp;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class TopUpTest extends TestCase
{
    public function testNewTopUpHasDefaultValues(): void
    {
        $topUp = new TopUp();

        $this->assertNotNull($topUp->getId());
        $this->assertSame(TopUp::STATUS_INITIATED, $topUp->getStatus());
        $this->assertSame('EUR', $topUp->getCurrency());
        $this->assertStringStartsWith('EUPAY-', $topUp->getReference());
        $this->assertNotNull($topUp->getCreatedAt());
        $this->assertNull($topUp->getCompletedAt());
        $this->assertNull($topUp->getFailureReason());
        $this->assertFalse($topUp->isTerminal());
    }

    public function testAmountConversion(): void
    {
        $topUp = new TopUp();
        $topUp->setAmountCents(2550);

        $this->assertSame(2550, $topUp->getAmountCents());
        $this->assertSame('25.50', $topUp->getAmountEur());
    }

    public function testIdealMethod(): void
    {
        $topUp = new TopUp();
        $topUp->setMethod(TopUp::METHOD_IDEAL);
        $topUp->setSourceBic('RABONL2U');

        $this->assertSame('ideal', $topUp->getMethod());
        $this->assertSame('RABONL2U', $topUp->getSourceBic());
    }

    public function testSepaMethod(): void
    {
        $topUp = new TopUp();
        $topUp->setMethod(TopUp::METHOD_SEPA);
        $topUp->setEncryptedSourceIban('encrypted_iban_blob');

        $this->assertSame('sepa_credit_transfer', $topUp->getMethod());
        $this->assertSame('encrypted_iban_blob', $topUp->getEncryptedSourceIban());
    }

    public function testStateTransitions(): void
    {
        $topUp = new TopUp();

        $topUp->markPending();
        $this->assertSame(TopUp::STATUS_PENDING, $topUp->getStatus());
        $this->assertFalse($topUp->isTerminal());

        $topUp->markCompleted('ext-tx-123');
        $this->assertSame(TopUp::STATUS_COMPLETED, $topUp->getStatus());
        $this->assertSame('ext-tx-123', $topUp->getExternalTransactionId());
        $this->assertNotNull($topUp->getCompletedAt());
        $this->assertTrue($topUp->isTerminal());
    }

    public function testFailedState(): void
    {
        $topUp = new TopUp();
        $topUp->markFailed('Insufficient funds');

        $this->assertSame(TopUp::STATUS_FAILED, $topUp->getStatus());
        $this->assertSame('Insufficient funds', $topUp->getFailureReason());
        $this->assertTrue($topUp->isTerminal());
    }

    public function testCancelledState(): void
    {
        $topUp = new TopUp();
        $topUp->markCancelled();

        $this->assertSame(TopUp::STATUS_CANCELLED, $topUp->getStatus());
        $this->assertTrue($topUp->isTerminal());
    }
}
