<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\CardRepository;
use App\Repository\HceTokenRepository;
use App\Repository\TransactionRepository;
use App\Service\HceProvisioningService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GDPR Data Subject Rights + EU Legal Compliance endpoints.
 *
 * Implements:
 * - Art. 15: Right of Access (data export)
 * - Art. 17: Right to Erasure ("right to be forgotten")
 * - Art. 20: Right to Data Portability (machine-readable export)
 * - Art. 7(3): Right to Withdraw Consent
 * - Art. 13/14: Transparency (privacy policy, legal basis info)
 *
 * Also:
 * - EU Consumer Rights Directive: 14-day withdrawal period info
 * - ePrivacy: device tracking consent management
 */
#[Route('/api')]
class GdprController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CardRepository $cardRepo,
        private readonly HceTokenRepository $hceTokenRepo,
        private readonly TransactionRepository $txRepo,
        private readonly HceProvisioningService $hceService,
        private readonly LoggerInterface $logger,
    ) {}

    // ──────────────────────────────────────────────────────
    // Art. 15 + Art. 20 — Right of Access & Data Portability
    // ──────────────────────────────────────────────────────

    /**
     * Export all personal data in machine-readable JSON (GDPR Art. 20).
     * Response must be in a "structured, commonly used, machine-readable format."
     */
    #[Route('/gdpr/export', methods: ['GET'])]
    public function exportData(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isAnonymized()) {
            return $this->json(['error' => 'Account data has been erased'], Response::HTTP_GONE);
        }

        $cards = $this->cardRepo->findActiveByUser($user);
        $transactions = $this->txRepo->findRecentByUser($user, 10000);

        $export = [
            '_meta' => [
                'format'       => 'GDPR Art. 20 Data Portability Export',
                'exported_at'  => (new \DateTimeImmutable())->format('c'),
                'data_controller' => 'EU Pay GmbH',
                'data_controller_address' => 'EU-based — see /api/legal/imprint',
                'dpo_contact'  => 'dpo@eupay.eu',
            ],
            'personal_data' => [
                'id'           => (string) $user->getId(),
                // Encrypted fields — client decrypts locally with private key
                'encrypted_email'      => $user->getEncryptedEmail(),
                'encrypted_first_name' => $user->getEncryptedFirstName(),
                'encrypted_last_name'  => $user->getEncryptedLastName(),
                'encrypted_phone'      => $user->getEncryptedPhoneNumber(),
                'encrypted_iban'       => $user->getEncryptedIban(),
                'kyc_status'   => $user->getKycStatus(),
                'created_at'   => $user->getCreatedAt()->format('c'),
                '_note' => 'Personal data fields are envelope-encrypted (RSA-4096 + AES-256-GCM). '
                    . 'Decrypt with your private key using the EuPay app.',
            ],
            'consent_records' => [
                'gdpr_consent'            => $user->isGdprConsentGiven(),
                'gdpr_consent_at'         => $user->getGdprConsentAt()?->format('c'),
                'privacy_policy_version'  => $user->getPrivacyPolicyVersion(),
                'device_tracking_consent' => $user->isDeviceTrackingConsent(),
                'marketing_consent'       => $user->isMarketingConsent(),
                'legal_basis'             => $user->getLegalBasis(),
            ],
            'cards' => array_map(fn($c) => [
                'id'         => (string) $c->getId(),
                'type'       => $c->getType(),
                'scheme'     => $c->getScheme(),
                'status'     => $c->getStatus(),
                'last_four'  => $c->getLastFourDigits(),
                'created_at' => $c->getCreatedAt()->format('c'),
            ], $cards),
            'transactions' => array_map(fn($tx) => [
                'id'                      => (string) $tx->getId(),
                'type'                    => $tx->getType(),
                'status'                  => $tx->getStatus(),
                'amount'                  => $tx->getAmount(),
                'currency'                => $tx->getCurrency(),
                'encrypted_merchant_name' => $tx->getEncryptedMerchantName(),
                'created_at'              => $tx->getCreatedAt()->format('c'),
            ], $transactions),
            'data_retention' => [
                'retention_until' => $user->getDataRetentionUntil()?->format('c'),
                'legal_basis'     => 'EU Anti-Money Laundering Directive 2015/849 — 5-year retention for financial records',
            ],
        ];

        return $this->json($export, Response::HTTP_OK, [
            'Content-Disposition' => 'attachment; filename="eupay-data-export.json"',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Art. 17 — Right to Erasure ("Right to be Forgotten")
    // ──────────────────────────────────────────────────────

    /**
     * Delete / anonymize the user's account and personal data.
     *
     * Note: Under EU AML Directive (2015/849), financial institutions must
     * retain transaction records for 5 years. We anonymize personal data
     * but preserve anonymized transaction records.
     *
     * Requires explicit confirmation to prevent accidental deletion.
     */
    #[Route('/gdpr/erase', methods: ['POST'])]
    public function eraseData(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        if (($data['confirm_deletion'] ?? false) !== true) {
            return $this->json([
                'error' => 'You must set confirm_deletion to true.',
                'warning' => 'This action is irreversible. All personal data will be erased. '
                    . 'Transaction records will be anonymized and retained for 5 years per EU AML regulations.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user->isAnonymized()) {
            return $this->json(['error' => 'Account already erased'], Response::HTTP_GONE);
        }

        $this->logger->info('GDPR erasure request', ['user_id' => (string) $user->getId()]);

        // Deactivate all HCE tokens (stop NFC payments immediately)
        $tokens = $this->hceTokenRepo->findActiveByUser($user);
        foreach ($tokens as $token) {
            $this->hceService->deactivateToken($token);
        }

        // Block all cards via PSD2 bank
        $cards = $this->cardRepo->findActiveByUser($user);
        foreach ($cards as $card) {
            $card->setStatus('CLOSED');
        }

        // Anonymize user — replaces all PII with placeholder data
        $user->anonymize();

        $this->em->flush();

        $this->logger->info('GDPR erasure completed', ['user_id' => (string) $user->getId()]);

        return $this->json([
            'status'  => 'erased',
            'message' => 'Your personal data has been erased. Anonymized financial records '
                . 'are retained for 5 years per EU Anti-Money Laundering regulations (Directive 2015/849).',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Art. 7(3) — Consent Management (Withdraw / Update)
    // ──────────────────────────────────────────────────────

    /**
     * View current consent settings.
     */
    #[Route('/gdpr/consent', methods: ['GET'])]
    public function getConsent(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'gdpr_consent'            => $user->isGdprConsentGiven(),
            'gdpr_consent_at'         => $user->getGdprConsentAt()?->format('c'),
            'privacy_policy_version'  => $user->getPrivacyPolicyVersion(),
            'device_tracking_consent' => $user->isDeviceTrackingConsent(),
            'marketing_consent'       => $user->isMarketingConsent(),
            'legal_basis'             => $user->getLegalBasis(),
            'info' => 'You can update or withdraw consent at any time. '
                . 'Withdrawing core processing consent will require account closure.',
        ]);
    }

    /**
     * Update consent preferences.
     * Per Art. 7(3), withdrawing consent must be as easy as giving it.
     *
     * Note: Core processing consent is based on contract necessity (Art. 6(1)(b)).
     * Only optional consents (marketing, device tracking) can be toggled freely.
     */
    #[Route('/gdpr/consent', methods: ['PATCH'])]
    public function updateConsent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['device_tracking_consent'])) {
            $user->setDeviceTrackingConsent((bool) $data['device_tracking_consent']);
        }

        if (isset($data['marketing_consent'])) {
            $user->setMarketingConsent((bool) $data['marketing_consent']);
        }

        $this->em->flush();

        return $this->json([
            'device_tracking_consent' => $user->isDeviceTrackingConsent(),
            'marketing_consent'       => $user->isMarketingConsent(),
            'updated_at'              => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Legal / Transparency Endpoints (public, no auth)
    // ──────────────────────────────────────────────────────

    /**
     * Privacy Policy summary — GDPR Art. 13/14 transparency.
     * In production, this would link to the full legal document.
     */
    #[Route('/legal/privacy-policy', methods: ['GET'])]
    public function privacyPolicy(): JsonResponse
    {
        return $this->json([
            'version' => '1.0.0',
            'effective_date' => '2026-01-01',
            'data_controller' => [
                'name'    => 'EU Pay GmbH',
                'address' => 'Musterstraße 1, 10115 Berlin, Germany',
                'email'   => 'privacy@eupay.eu',
                'dpo'     => 'dpo@eupay.eu',
            ],
            'supervisory_authority' => [
                'name'    => 'Berliner Beauftragte für Datenschutz und Informationsfreiheit',
                'website' => 'https://www.datenschutz-berlin.de',
            ],
            'data_processed' => [
                'Name, email, phone number — for account creation and KYC',
                'IBAN, card data — for payment services (processed by PSD2 Open Banking, Germany)',
                'Device fingerprint (with consent) — for payment token security',
                'Transaction history — for account management',
            ],
            'legal_bases' => [
                'Art. 6(1)(b) — Processing necessary for contract performance (payment services)',
                'Art. 6(1)(c) — Legal obligation (AML/KYC regulations)',
                'Art. 6(1)(a) — Consent (device tracking, marketing — optional)',
            ],
            'data_recipients' => [
                'PSD2 compliant banks (formerly PSD2 Open Banking) — licensed bank, Berlin, Germany — EU data only',
                'No data is transferred outside the EU/EEA.',
            ],
            'retention' => [
                'Account data: duration of contract + 5 years (EU AML Directive 2015/849)',
                'Transaction records: 10 years (German commercial law, HGB §257)',
                'Marketing consent logs: 3 years after withdrawal',
            ],
            'your_rights' => [
                'Access (Art. 15)' => 'GET /api/gdpr/export',
                'Erasure (Art. 17)' => 'POST /api/gdpr/erase',
                'Data Portability (Art. 20)' => 'GET /api/gdpr/export',
                'Consent Management (Art. 7)' => 'GET/PATCH /api/gdpr/consent',
                'Rectification (Art. 16)' => 'Contact dpo@eupay.eu',
                'Object to Processing (Art. 21)' => 'Contact dpo@eupay.eu',
                'Lodge a Complaint' => 'You have the right to lodge a complaint with your local supervisory authority.',
            ],
            'cookies_and_tracking' => 'The EuPay mobile app does not use cookies. '
                . 'Device fingerprinting requires explicit opt-in consent (ePrivacy Directive, Art. 5(3)).',
        ]);
    }

    /**
     * Legal imprint (Impressum) — required in Germany (TMG §5).
     */
    #[Route('/legal/imprint', methods: ['GET'])]
    public function imprint(): JsonResponse
    {
        return $this->json([
            'company'   => 'EU Pay GmbH',
            'address'   => 'Musterstraße 1, 10115 Berlin, Germany',
            'register'  => 'Amtsgericht Charlottenburg, HRB XXXXXX',
            'vat_id'    => 'DE XXXXXXXXX',
            'managing_directors' => ['[Name]'],
            'email'     => 'contact@eupay.eu',
            'phone'     => '+49 30 XXXXXXXX',
            'regulatory_authority' => 'BaFin (Bundesanstalt für Finanzdienstleistungsaufsicht)',
            'banking_partner' => 'PSD2 Open Banking — all EU/EEA licensed banks',
        ]);
    }

    /**
     * EU Consumer Rights — 14-day withdrawal information.
     * Required under EU Consumer Rights Directive (2011/83/EU) for distance contracts.
     */
    #[Route('/legal/withdrawal', methods: ['GET'])]
    public function withdrawalRights(): JsonResponse
    {
        return $this->json([
            'right_of_withdrawal' => [
                'period' => '14 calendar days from contract conclusion',
                'directive' => 'EU Consumer Rights Directive 2011/83/EU, Art. 9',
                'exception' => 'For payment services already fully performed with your explicit consent, '
                    . 'the right of withdrawal may expire early (Art. 16(a)).',
                'how_to_withdraw' => 'Send a clear statement to contact@eupay.eu or use the in-app account deletion.',
                'effects' => 'Upon withdrawal, your account will be closed and personal data erased '
                    . '(subject to AML record retention requirements).',
            ],
            'dispute_resolution' => [
                'eu_odr_platform' => 'https://ec.europa.eu/consumers/odr',
                'note' => 'We are not obliged to participate in dispute resolution proceedings '
                    . 'before a consumer arbitration board but may choose to do so.',
            ],
        ]);
    }
}
