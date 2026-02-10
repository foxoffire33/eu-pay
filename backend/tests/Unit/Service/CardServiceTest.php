<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Card;
use App\Entity\User;
use App\Repository\CardRepository;
use App\Service\CardIssuing\CardIssuerInterface;
use App\Service\CardService;
use App\Service\DirectDebitService;
use App\Service\LinkedBankAccountService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CardServiceTest extends TestCase
{
    private CardIssuerInterface&MockObject $issuer;
    private LinkedBankAccountService&MockObject $bankAccountService;
    private DirectDebitService&MockObject $directDebitService;
    private EntityManagerInterface&MockObject $em;
    private CardRepository&MockObject $cardRepo;
    private CardService $service;

    protected function setUp(): void
    {
        $this->issuer = $this->createMock(CardIssuerInterface::class);
        $this->bankAccountService = $this->createMock(LinkedBankAccountService::class);
        $this->directDebitService = $this->createMock(DirectDebitService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->cardRepo = $this->createMock(CardRepository::class);

        $this->service = new CardService(
            $this->issuer,
            $this->bankAccountService,
            $this->directDebitService,
            $this->em,
            $this->cardRepo,
            new NullLogger(),
        );
    }

    public function testCreateVirtualCard(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(new \Symfony\Component\Uid\UuidV6());
        $user->method('getExternalAccountId')->willReturn('iban-123');

        // Simulate active bank account and mandate
        $activeAccount = $this->createMock(\App\Entity\LinkedBankAccount::class);
        $this->bankAccountService->method('getActiveAccount')->willReturn($activeAccount);
        $this->directDebitService->method('hasActiveMandate')->willReturn(true);

        $this->issuer->method('createVirtualCard')->willReturn([
            'cardId' => 'marqeta-card-abc',
            'status' => 'ACTIVE',
            'maskedPan' => '************1234',
            'last4' => '1234',
            'expiryMonth' => 12,
            'expiryYear' => 2028,
            'scheme' => 'VISA',
        ]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $card = $this->service->createVirtualCard($user, 'Jan de Vries');

        $this->assertSame('marqeta-card-abc', $card->getExternalCardId());
        $this->assertSame('1234', $card->getLastFourDigits());
        $this->assertSame('VISA', $card->getScheme());
        $this->assertSame('ACTIVE', $card->getStatus());
        $this->assertSame('VIRTUAL', $card->getType());
    }

    public function testBlockCard(): void
    {
        $card = new Card();
        $card->setExternalCardId('card-abc');
        $card->setStatus('ACTIVE');

        $this->issuer->expects($this->once())->method('blockCard')
            ->with('card-abc')
            ->willReturn(['cardId' => 'card-abc', 'status' => 'SUSPENDED']);

        $result = $this->service->blockCard($card);
        $this->assertSame('BLOCKED', $result->getStatus());
    }

    public function testUnblockCard(): void
    {
        $card = new Card();
        $card->setExternalCardId('card-abc');
        $card->setStatus('BLOCKED');

        $this->issuer->expects($this->once())->method('unblockCard')
            ->willReturn(['cardId' => 'card-abc', 'status' => 'ACTIVE']);

        $result = $this->service->unblockCard($card);
        $this->assertSame('ACTIVE', $result->getStatus());
    }

    public function testActivateCard(): void
    {
        $card = new Card();
        $card->setExternalCardId('card-abc');

        $this->issuer->expects($this->once())->method('activateCard')
            ->willReturn(['cardId' => 'card-abc', 'status' => 'ACTIVE']);

        $result = $this->service->activateCard($card, '123456');
        $this->assertSame('ACTIVE', $result->getStatus());
    }

    public function testLoadFunds(): void
    {
        $card = new Card();
        $card->setExternalCardId('card-abc');

        $this->issuer->expects($this->once())->method('loadFunds')
            ->with('card-abc', 5000, 'EUR')
            ->willReturn(['transactionId' => 'tx-123', 'amount' => 50.00, 'status' => 'COMPLETED']);

        $result = $this->service->loadFunds($card, 5000);
        $this->assertSame('tx-123', $result['transactionId']);
    }

    public function testSyncCardStatus(): void
    {
        $card = new Card();
        $card->setExternalCardId('card-abc');
        $card->setStatus('ACTIVE');

        $this->cardRepo->method('findByExternalCardId')->willReturn($card);
        $this->issuer->method('getCard')->willReturn([
            'cardId' => 'card-abc',
            'status' => 'SUSPENDED',
            'last4' => '1234',
            'expiryMonth' => 12,
            'expiryYear' => 2028,
            'scheme' => 'VISA',
        ]);

        $result = $this->service->syncCardStatus('card-abc');
        $this->assertSame('SUSPENDED', $result->getStatus());
    }
}
