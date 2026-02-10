<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Card;
use App\Entity\HceToken;
use App\Entity\User;
use App\Repository\HceTokenRepository;
use App\Service\CardEncryptionService;
use App\Service\CardIssuing\CardIssuerInterface;
use App\Service\HceProvisioningService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class HceProvisioningServiceTest extends TestCase
{
    private CardIssuerInterface&MockObject $issuer;
    private EntityManagerInterface&MockObject $em;
    private HceTokenRepository&MockObject $hceTokenRepo;
    private CardEncryptionService&MockObject $encryption;
    private HceProvisioningService $service;

    protected function setUp(): void
    {
        $this->issuer = $this->createMock(CardIssuerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->hceTokenRepo = $this->createMock(HceTokenRepository::class);
        $this->encryption = $this->createMock(CardEncryptionService::class);

        $this->service = new HceProvisioningService(
            $this->issuer,
            $this->em,
            $this->hceTokenRepo,
            $this->encryption,
            new NullLogger(),
            300,
        );
    }

    public function testProvisionCreatesDpanToken(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(new \Symfony\Component\Uid\UuidV6());

        $card = new Card();
        $card->setExternalCardId('card-abc');
        $card->setUser($user);
        $card->setScheme('VISA');

        $this->issuer->method('getCard')->willReturn(['status' => 'ACTIVE']);
        $this->hceTokenRepo->method('findActiveByCardAndDevice')->willReturn([]);

        $this->issuer->method('provisionDigitalCard')->willReturn([
            'dpan' => '4900123456789012',
            'dpanExpiryMonth' => 12,
            'dpanExpiryYear' => 2028,
            'tokenReferenceId' => 'dpan-ref-xyz',
            'tokenStatus' => 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => 'enc_icc_priv',
                'iccCertificate' => 'enc_icc_cert',
                'issuerPublicKey' => 'enc_issuer_pub',
            ],
        ]);

        $this->issuer->method('generateEmvSessionKeys')->willReturn([
            'sessionKey' => 'session_key_hex',
            'arqc' => 'arqc_hex',
            'atc' => 1,
            'unpredictableNumber' => 'abcd1234',
        ]);

        $this->encryption->method('encrypt')->willReturn('encrypted_data');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $token = $this->service->provisionCard($user, $card, 'device-fp-123');

        $this->assertSame('ACTIVE', $token->getStatus());
        $this->assertSame('4900123456789012', $token->getDpan());
        $this->assertSame('dpan-ref-xyz', $token->getTokenReferenceId());
        $this->assertSame('card-abc', $token->getExternalCardId());
        $this->assertSame('VISA', $token->getCardScheme());
        $this->assertSame(1, $token->getAtc());
    }

    public function testProvisionFailsForInactiveCard(): void
    {
        $user = $this->createMock(User::class);
        $card = new Card();
        $card->setExternalCardId('card-abc');

        $this->issuer->method('getCard')->willReturn(['status' => 'SUSPENDED']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not active');

        $this->service->provisionCard($user, $card, 'device-123');
    }

    public function testRefreshGeneratesNewSessionKeys(): void
    {
        $card = new Card();
        $card->setExternalCardId('card-abc');

        $token = new HceToken();
        $token->setCard($card);
        $token->setExternalCardId('card-abc');
        $token->setTokenReferenceId('dpan-ref-xyz');
        $token->setStatus('ACTIVE');
        $token->setAtc(5);
        $token->setDpan('4900123456789012');
        $token->setSessionKey('old_key');

        $this->issuer->method('getDigitalCardStatus')->willReturn([
            'tokenReferenceId' => 'dpan-ref-xyz',
            'status' => 'ACTIVE',
        ]);

        $this->issuer->method('generateEmvSessionKeys')->willReturn([
            'sessionKey' => 'new_session_key',
            'arqc' => 'new_arqc',
            'atc' => 6,
            'unpredictableNumber' => 'ef012345',
        ]);

        $this->encryption->method('encrypt')->willReturn('encrypted_new_key');

        $result = $this->service->refreshSessionKey($token);

        $this->assertSame(6, $result['atc']);
        $this->assertArrayHasKey('sessionKey', $result);
        $this->assertArrayHasKey('expiresAt', $result);
    }

    public function testDeactivateToken(): void
    {
        $token = new HceToken();
        $token->setTokenReferenceId('dpan-ref-xyz');
        $token->setStatus('ACTIVE');
        $token->setDpan('4900123456789012');
        $token->setSessionKey('key');
        $token->setExternalCardId('card-abc');

        $this->issuer->expects($this->once())->method('deactivateDigitalCard')
            ->with('dpan-ref-xyz');

        $this->service->deactivateToken($token);
        $this->assertSame('DEACTIVATED', $token->getStatus());
    }
}
