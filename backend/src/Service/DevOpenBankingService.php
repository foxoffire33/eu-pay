<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Development stub for PSD2 Open Banking — returns mock data.
 *
 * Used when no real PSD2 API keys (Rabobank sandbox) are configured.
 * Simulates AISP account reads and PISP payment initiation.
 */
class DevOpenBankingService implements OpenBankingInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function initiateSepaTransfer(
        string $debtorIban,
        string $debtorName,
        string $creditorIban,
        string $creditorName,
        string $amountEur,
        string $reference,
    ): array {
        $paymentId = 'dev_pmt_' . bin2hex(random_bytes(8));
        $this->logger->info('DEV: SEPA transfer initiated (mock)', [
            'paymentId' => $paymentId,
            'amount' => $amountEur,
        ]);

        return [
            'paymentId' => $paymentId,
            'authorisationUrl' => 'http://localhost:8080/dev-sca?payment=' . $paymentId,
            'status' => 'RCVD',
        ];
    }

    public function initiateIdealPayment(
        string $creditorIban,
        string $creditorName,
        string $amountEur,
        string $reference,
        ?string $debtorBic = null,
    ): array {
        $paymentId = 'dev_ideal_' . bin2hex(random_bytes(8));
        $this->logger->info('DEV: iDEAL payment initiated (mock)', [
            'paymentId' => $paymentId,
            'amount' => $amountEur,
        ]);

        return [
            'paymentId' => $paymentId,
            'authorisationUrl' => 'http://localhost:8080/dev-sca?payment=' . $paymentId,
            'status' => 'RCVD',
        ];
    }

    public function getPaymentStatus(string $paymentProduct, string $paymentId): array
    {
        return [
            'paymentId' => $paymentId,
            'status' => 'ACSC',
        ];
    }

    public function createAccountConsent(string $iban, int $validDays = 90): array
    {
        $consentId = 'dev_consent_' . bin2hex(random_bytes(8));
        $validUntil = (new \DateTimeImmutable())
            ->modify("+{$validDays} days")
            ->format('Y-m-d');

        $this->logger->info('DEV: AISP consent created (mock)', [
            'consentId' => $consentId,
            'iban' => substr($iban, 0, 4) . '****',
        ]);

        return [
            'consentId' => $consentId,
            'authorisationUrl' => 'http://localhost:8080/dev-sca?consent=' . $consentId,
            'validUntil' => $validUntil,
        ];
    }

    public function getAccountBalance(string $consentId, string $accountId): array
    {
        return [
            [
                'balanceType' => 'closingBooked',
                'balanceAmount' => [
                    'currency' => 'EUR',
                    'amount' => '1234.56',
                ],
            ],
            [
                'balanceType' => 'expected',
                'balanceAmount' => [
                    'currency' => 'EUR',
                    'amount' => '1234.56',
                ],
            ],
        ];
    }

    public function getAccountTransactions(
        string $consentId,
        string $accountId,
        string $dateFrom,
        ?string $dateTo = null,
    ): array {
        $today = new \DateTimeImmutable();

        return [
            [
                'transactionId' => 'dev_tx_001',
                'bookingDate' => $today->modify('-1 day')->format('Y-m-d'),
                'transactionAmount' => ['currency' => 'EUR', 'amount' => '-42.50'],
                'creditorName' => 'Albert Heijn',
                'remittanceInformationUnstructured' => 'Contactless payment',
            ],
            [
                'transactionId' => 'dev_tx_002',
                'bookingDate' => $today->modify('-2 days')->format('Y-m-d'),
                'transactionAmount' => ['currency' => 'EUR', 'amount' => '-15.90'],
                'creditorName' => 'NS Reizigers',
                'remittanceInformationUnstructured' => 'OV-Chipkaart reizen',
            ],
            [
                'transactionId' => 'dev_tx_003',
                'bookingDate' => $today->modify('-3 days')->format('Y-m-d'),
                'transactionAmount' => ['currency' => 'EUR', 'amount' => '-89.99'],
                'creditorName' => 'Bol.com',
                'remittanceInformationUnstructured' => 'Bestelling #123456',
            ],
            [
                'transactionId' => 'dev_tx_004',
                'bookingDate' => $today->modify('-5 days')->format('Y-m-d'),
                'transactionAmount' => ['currency' => 'EUR', 'amount' => '2500.00'],
                'debtorName' => 'Werkgever B.V.',
                'remittanceInformationUnstructured' => 'Salaris februari',
            ],
            [
                'transactionId' => 'dev_tx_005',
                'bookingDate' => $today->modify('-7 days')->format('Y-m-d'),
                'transactionAmount' => ['currency' => 'EUR', 'amount' => '-120.00'],
                'creditorName' => 'Vattenfall',
                'remittanceInformationUnstructured' => 'Maandbedrag energie',
            ],
        ];
    }

    public function getSupportedBanks(): array
    {
        return EuBankRegistry::getAll();
    }

    public function getBanksByCountry(string $countryCode): array
    {
        return EuBankRegistry::getByCountry($countryCode);
    }

    public function getSupportedCountries(): array
    {
        return EuBankRegistry::getSupportedCountries();
    }
}
