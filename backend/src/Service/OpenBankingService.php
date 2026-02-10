<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * PSD2 Open Banking integration — PISP (Payment Initiation) + AISP (Account Information).
 *
 * Supports:
 *  - Rabobank PSD2 API (primary Dutch bank)
 *  - iDEAL 2.0 via Rabobank as ASPSP
 *  - Generic SEPA Credit Transfer initiation
 *  - Account balance/transaction reads (AISP) for connected accounts
 *
 * @see https://developer.rabobank.nl/ — Rabobank PSD2 sandbox + production
 * @see https://www.berlin-group.org/ — NextGenPSD2 specification
 */
class OpenBankingService
{
    private const RABOBANK_SANDBOX_URL = 'https://api-sandbox.rabobank.nl';
    private const RABOBANK_PRODUCTION_URL = 'https://api.rabobank.nl';

    // PSD2 consent validity — max 90 days per RTS
    private const CONSENT_MAX_DAYS = 90;

    // iDEAL payment method identifier
    private const PAYMENT_PRODUCT_IDEAL = 'ideal-payments';
    private const PAYMENT_PRODUCT_SEPA = 'sepa-credit-transfers';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $rabobankClientId,
        private readonly string $rabobankClientSecret,
        private readonly string $rabobankCertPath,
        private readonly string $rabobankKeyPath,
        private readonly string $rabobankApiBaseUrl,
        private readonly string $appCallbackUrl,
    ) {
    }

    // ── PISP: Payment Initiation ────────────────────────

    /**
     * Initiate a SEPA Credit Transfer via PSD2.
     *
     * @return array{paymentId: string, authorisationUrl: string, status: string}
     */
    public function initiateSepaTransfer(
        string $debtorIban,
        string $debtorName,
        string $creditorIban,
        string $creditorName,
        string $amountEur,
        string $reference,
    ): array {
        $payload = [
            'instructedAmount' => [
                'currency' => 'EUR',
                'amount' => $amountEur,
            ],
            'debtorAccount' => [
                'iban' => $debtorIban,
            ],
            'debtorName' => $debtorName,
            'creditorAccount' => [
                'iban' => $creditorIban,
            ],
            'creditorName' => $creditorName,
            'remittanceInformationUnstructured' => $reference,
        ];

        return $this->initiatePayment(self::PAYMENT_PRODUCT_SEPA, $payload);
    }

    /**
     * Initiate an iDEAL payment (Dutch instant bank transfer).
     *
     * iDEAL 2.0 runs through the PSD2 PISP flow:
     *  1. Create payment initiation → get authorisation URL
     *  2. Redirect user to bank (Rabobank, ING, ABN AMRO, etc.)
     *  3. User authenticates via SCA (banking app / card reader)
     *  4. Callback with payment status
     *
     * @return array{paymentId: string, authorisationUrl: string, status: string}
     */
    public function initiateIdealPayment(
        string $creditorIban,
        string $creditorName,
        string $amountEur,
        string $reference,
        ?string $debtorBic = null,
    ): array {
        $payload = [
            'instructedAmount' => [
                'currency' => 'EUR',
                'amount' => $amountEur,
            ],
            'creditorAccount' => [
                'iban' => $creditorIban,
            ],
            'creditorName' => $creditorName,
            'remittanceInformationUnstructured' => $reference,
        ];

        // Optional: pre-select bank by BIC (e.g., RABONL2U for Rabobank)
        if ($debtorBic !== null) {
            $payload['debtorAgent'] = $debtorBic;
        }

        return $this->initiatePayment(self::PAYMENT_PRODUCT_IDEAL, $payload);
    }

    /**
     * Get payment status after SCA redirect callback.
     */
    public function getPaymentStatus(string $paymentProduct, string $paymentId): array
    {
        $response = $this->request(
            'GET',
            "/v3/payments/{$paymentProduct}/{$paymentId}/status"
        );

        return [
            'paymentId' => $paymentId,
            'status' => $response['transactionStatus'] ?? 'UNKNOWN',
        ];
    }

    // ── AISP: Account Information ───────────────────────

    /**
     * Create an AISP consent to read account data.
     * User must authorise this via SCA at their bank.
     *
     * @return array{consentId: string, authorisationUrl: string, validUntil: string}
     */
    public function createAccountConsent(
        string $iban,
        int $validDays = self::CONSENT_MAX_DAYS,
    ): array {
        $validUntil = (new \DateTimeImmutable())
            ->modify("+{$validDays} days")
            ->format('Y-m-d');

        $payload = [
            'access' => [
                'accounts' => [['iban' => $iban]],
                'balances' => [['iban' => $iban]],
                'transactions' => [['iban' => $iban]],
            ],
            'recurringIndicator' => true,
            'validUntil' => $validUntil,
            'frequencyPerDay' => 4,
            'combinedServiceIndicator' => false,
        ];

        $response = $this->request('POST', '/v3/consents', $payload);

        $consentId = $response['consentId'] ?? '';
        $authorisationUrl = $response['_links']['scaRedirect']['href'] ?? '';

        return [
            'consentId' => $consentId,
            'authorisationUrl' => $authorisationUrl,
            'validUntil' => $validUntil,
        ];
    }

    /**
     * Read account balance via AISP.
     */
    public function getAccountBalance(string $consentId, string $accountId): array
    {
        $response = $this->request(
            'GET',
            "/v3/accounts/{$accountId}/balances",
            headers: ['Consent-ID' => $consentId]
        );

        return $response['balances'] ?? [];
    }

    /**
     * List account transactions via AISP.
     */
    public function getAccountTransactions(
        string $consentId,
        string $accountId,
        string $dateFrom,
        ?string $dateTo = null,
    ): array {
        $query = ['dateFrom' => $dateFrom, 'bookingStatus' => 'booked'];
        if ($dateTo !== null) {
            $query['dateTo'] = $dateTo;
        }

        $response = $this->request(
            'GET',
            "/v3/accounts/{$accountId}/transactions?" . http_build_query($query),
            headers: ['Consent-ID' => $consentId]
        );

        return $response['transactions']['booked'] ?? [];
    }

    // ── Supported Banks ─────────────────────────────────

    /**
     * All EU/EEA PSD2-compliant banks.
     * PSD2 is mandatory — every licensed bank in the EU/EEA must support XS2A.
     *
     * @return array<array{bic: string, name: string, country: string, country_name: string}>
     */
    public function getSupportedBanks(): array
    {
        return EuBankRegistry::getAll();
    }

    /**
     * Banks filtered by country.
     */
    public function getBanksByCountry(string $countryCode): array
    {
        return EuBankRegistry::getByCountry($countryCode);
    }

    /**
     * Supported country codes.
     *
     * @return string[]
     */
    public function getSupportedCountries(): array
    {
        return EuBankRegistry::getSupportedCountries();
    }

    // ── Internal ────────────────────────────────────────

    private function initiatePayment(string $paymentProduct, array $payload): array
    {
        $response = $this->request(
            'POST',
            "/v3/payments/{$paymentProduct}",
            $payload,
            [
                'TPP-Redirect-URI' => $this->appCallbackUrl . '/topup/callback',
                'TPP-Nok-Redirect-URI' => $this->appCallbackUrl . '/topup/callback?error=cancelled',
            ]
        );

        return [
            'paymentId' => $response['paymentId'] ?? '',
            'authorisationUrl' => $response['_links']['scaRedirect']['href'] ?? '',
            'status' => $response['transactionStatus'] ?? 'RCVD',
        ];
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
    ): array {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'X-Request-ID' => \Symfony\Component\Uid\Uuid::v6()->toRfc4122(),
            'PSU-IP-Address' => '127.0.0.1',
        ];

        $options = [
            'headers' => array_merge($defaultHeaders, $headers),
            'auth_basic' => [$this->rabobankClientId, $this->rabobankClientSecret],
        ];

        // Mutual TLS (eIDAS QWAC certificate required for PSD2)
        if (file_exists($this->rabobankCertPath)) {
            $options['local_cert'] = $this->rabobankCertPath;
            $options['local_pk'] = $this->rabobankKeyPath;
        }

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request(
                $method,
                $this->rabobankApiBaseUrl . $path,
                $options
            );

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Open Banking API error', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "Open Banking request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }
}
