<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Swan — Embedded Banking-as-a-Service.
 *
 * License:  ACPR (Autorité de Contrôle Prudentiel et de Résolution), France (EMI)
 * Scheme:   Mastercard
 * Coverage: EU/EEA (passported from France)
 * Powers:   Pennylane, Agicap, Carrefour, Expensya, Friday Finance
 * Funding:  €108M+ raised (Accel, Lakestar, Creandum)
 *
 * Swan offers accounts, cards, and IBANs with a few lines of code.
 * GraphQL-first API with white-label UI components.
 * Non-bank: holds e-money licence, cannot issue credit.
 *
 * @see https://docs.swan.io/
 */
class SwanCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $projectId,
        private readonly string $accessToken,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        // Swan uses GraphQL — we use the REST-compatible wrapper
        $mutation = $this->graphql('addVirtualCard', [
            'accountMembershipId' => $userId,
            'name' => $cardholderName,
            'spending' => ['amount' => ['value' => '10000', 'currency' => $currency]],
        ]);

        $card = $mutation['addVirtualCard']['card'] ?? [];
        return [
            'cardId' => $card['id'] ?? '',
            'status' => strtoupper($card['statusInfo']['status'] ?? 'ENABLED'),
            'maskedPan' => $card['maskedCardNumber'] ?? '',
            'last4' => $card['last4'] ?? '',
            'expiryMonth' => (int) ($card['expiryDate']['month'] ?? 12),
            'expiryYear' => (int) ($card['expiryDate']['year'] ?? 2028),
            'scheme' => 'MASTERCARD',
        ];
    }

    public function activateCard(string $cardId): array
    {
        $this->graphql('enableCard', ['cardId' => $cardId]);
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->graphql('suspendCard', ['cardId' => $cardId]);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        return $this->activateCard($cardId);
    }

    public function terminateCard(string $cardId): array
    {
        $this->graphql('cancelCard', ['cardId' => $cardId]);
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $result = $this->graphql('card', ['cardId' => $cardId]);
        $c = $result['card'] ?? [];
        return [
            'cardId' => $c['id'] ?? '',
            'status' => strtoupper($c['statusInfo']['status'] ?? 'UNKNOWN'),
            'last4' => $c['last4'] ?? '',
            'expiryMonth' => (int) ($c['expiryDate']['month'] ?? 0),
            'expiryYear' => (int) ($c['expiryDate']['year'] ?? 0),
            'scheme' => 'MASTERCARD',
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $response = $this->graphql('addDigitalCard', [
            'cardId' => $cardId,
            'deviceId' => $deviceId,
            'walletProvider' => 'CUSTOM_HCE',
        ]);

        $dc = $response['addDigitalCard']['digitalCard'] ?? [];
        return [
            'dpan' => $dc['dpan'] ?? '',
            'dpanExpiryMonth' => (int) ($dc['expiryMonth'] ?? 12),
            'dpanExpiryYear' => (int) ($dc['expiryYear'] ?? 2028),
            'tokenReferenceId' => $dc['id'] ?? '',
            'tokenStatus' => 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => $dc['emvKeys']['iccPrivateKey'] ?? '',
                'iccCertificate' => $dc['emvKeys']['iccCertificate'] ?? '',
                'issuerPublicKey' => $dc['emvKeys']['issuerPublicKey'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->graphql('generateEmvSession', [
            'digitalCardId' => $tokenReferenceId,
            'atc' => $currentAtc + 1,
        ]);

        $session = $response['generateEmvSession'] ?? [];
        return [
            'sessionKey' => $session['sessionKey'] ?? '',
            'arqc' => $session['arqc'] ?? '',
            'atc' => (int) ($session['atc'] ?? $currentAtc + 1),
            'unpredictableNumber' => $session['unpredictableNumber'] ?? bin2hex(random_bytes(4)),
        ];
    }

    public function deactivateDigitalCard(string $tokenReferenceId): array
    {
        $this->graphql('cancelDigitalCard', ['digitalCardId' => $tokenReferenceId]);
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $result = $this->graphql('digitalCard', ['digitalCardId' => $tokenReferenceId]);
        return [
            'tokenReferenceId' => $tokenReferenceId,
            'status' => strtoupper($result['digitalCard']['status'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $response = $this->graphql('initiateFundingRequest', [
            'cardId' => $cardId,
            'amount' => ['value' => (string) ($amountCents / 100), 'currency' => $currency],
        ]);

        return [
            'transactionId' => $response['initiateFundingRequest']['id'] ?? '',
            'amount' => $amountCents / 100,
            'status' => 'COMPLETED',
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $result = $this->graphql('card', ['cardId' => $cardId]);
        $balance = $result['card']['accountMembership']['account']['balances'] ?? [];
        return [
            'available' => (int) (($balance['available']['value'] ?? 0) * 100),
            'ledger' => (int) (($balance['booked']['value'] ?? 0) * 100),
            'currency' => $balance['available']['currency'] ?? 'EUR',
        ];
    }

    private function graphql(string $operation, array $variables = []): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/api/graphql', [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                    'X-Project-Id' => $this->projectId,
                ],
                'json' => [
                    'operationName' => $operation,
                    'variables' => $variables,
                ],
            ]);

            $data = $response->toArray();
            if (!empty($data['errors'])) {
                throw new \RuntimeException($data['errors'][0]['message'] ?? 'GraphQL error');
            }
            return $data['data'] ?? [];
        } catch (\Throwable $e) {
            $this->logger->error('Swan API error', [
                'operation' => $operation, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Swan error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
