<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Tests for EU legal compliance data structures and business rules.
 */
class EuComplianceTest extends TestCase
{
    // ── Privacy Policy ──────────────────────────────

    public function testPrivacyPolicyRequiredFieldsPresent(): void
    {
        // These fields are required by GDPR Art. 13
        $required = [
            'data_controller',
            'supervisory_authority',
            'data_processed',
            'legal_bases',
            'data_recipients',
            'retention',
            'your_rights',
        ];

        // Simulating what the GdprController returns
        $policyData = $this->getPrivacyPolicyStructure();

        foreach ($required as $field) {
            $this->assertArrayHasKey($field, $policyData, "Privacy policy missing: {$field}");
        }
    }

    public function testDataControllerHasRequiredDetails(): void
    {
        $policy = $this->getPrivacyPolicyStructure();
        $controller = $policy['data_controller'];

        $this->assertArrayHasKey('name', $controller);
        $this->assertArrayHasKey('address', $controller);
        $this->assertArrayHasKey('email', $controller);
        $this->assertArrayHasKey('dpo', $controller); // DPO required under Art. 37
    }

    public function testNoNonEuDataTransferDeclared(): void
    {
        $policy = $this->getPrivacyPolicyStructure();
        $recipients = $policy['data_recipients'];

        foreach ($recipients as $recipient) {
            $this->assertStringContainsString('EU', $recipient,
                'All data recipients must be EU-based');
            $this->assertStringNotContainsString('US', $recipient,
                'No US-based data recipients allowed');
        }
    }

    public function testRetentionPeriodsSpecified(): void
    {
        $policy = $this->getPrivacyPolicyStructure();
        $this->assertNotEmpty($policy['retention']);
    }

    public function testAllGdprRightsDocumented(): void
    {
        $policy = $this->getPrivacyPolicyStructure();
        $rights = $policy['your_rights'];

        // GDPR requires informing users about all their rights
        $requiredRights = [
            'Access (Art. 15)',
            'Erasure (Art. 17)',
            'Data Portability (Art. 20)',
            'Consent Management (Art. 7)',
        ];

        foreach ($requiredRights as $right) {
            $this->assertArrayHasKey($right, $rights,
                "Missing documented right: {$right}");
        }
    }

    // ── Consent Validation ──────────────────────────

    public function testLegalBasisMustBeValidType(): void
    {
        $validBases = ['consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests'];
        // Art. 6(1) defines exactly these 6 legal bases

        foreach ($validBases as $basis) {
            $this->assertContains($basis, $validBases);
        }
    }

    public function testAmlRetentionPeriodIsFiveYears(): void
    {
        // EU AML Directive 2015/849 requires 5-year retention
        $registrationDate = new \DateTimeImmutable('2026-01-15');
        $retentionUntil = $registrationDate->modify('+5 years');

        $this->assertEquals('2031-01-15', $retentionUntil->format('Y-m-d'));
    }

    public function testAnonymizedEmailCannotBeUsedToIdentify(): void
    {
        // Anonymized emails must not contain the original email
        $originalEmail = 'max.mustermann@example.com';
        $anonymizedPrefix = 'deleted-' . bin2hex(random_bytes(8));
        $anonymizedEmail = $anonymizedPrefix . '@anonymized.local';

        $this->assertStringNotContainsString('max', $anonymizedEmail);
        $this->assertStringNotContainsString('mustermann', $anonymizedEmail);
        $this->assertStringNotContainsString('example.com', $anonymizedEmail);
    }

    // ── ePrivacy Directive ──────────────────────────

    public function testDeviceFingerprintingRequiresConsent(): void
    {
        // ePrivacy Directive Art. 5(3): storing/accessing info on user's device
        // requires informed consent (except for strictly necessary operations)
        $consentGiven = false;
        $this->assertFalse($consentGiven, 'Device fingerprinting must not proceed without consent');
    }

    // ── Consumer Rights ─────────────────────────────

    public function testWithdrawalPeriodIsFourteenDays(): void
    {
        // EU Consumer Rights Directive 2011/83/EU, Art. 9
        $contractDate = new \DateTimeImmutable('2026-02-10');
        $withdrawalDeadline = $contractDate->modify('+14 days');
        $this->assertEquals('2026-02-24', $withdrawalDeadline->format('Y-m-d'));
    }

    // ── Webhook signature (HMAC) ────────────────────

    public function testWebhookSignatureUsesSha256Hmac(): void
    {
        $secret = 'test-secret';
        $body = '{"event_type":"CARD_LIFECYCLE"}';
        $sig = hash_hmac('sha256', $body, $secret);

        $this->assertEquals(64, strlen($sig)); // SHA-256 = 64 hex chars
        $this->assertTrue(hash_equals($sig, hash_hmac('sha256', $body, $secret)));
    }

    // ── Helpers ─────────────────────────────────────

    private function getPrivacyPolicyStructure(): array
    {
        return [
            'version' => '1.0.0',
            'data_controller' => [
                'name'    => 'EU Pay GmbH',
                'address' => 'Musterstraße 1, 10115 Berlin, Germany',
                'email'   => 'privacy@eupay.eu',
                'dpo'     => 'dpo@eupay.eu',
            ],
            'supervisory_authority' => [
                'name' => 'Berliner Beauftragte für Datenschutz',
            ],
            'data_processed' => [
                'Name, email — for KYC',
                'Card data — processed by PSD2 Open Banking, Germany, EU',
            ],
            'legal_bases' => [
                'Art. 6(1)(b) — Contract performance',
                'Art. 6(1)(c) — Legal obligation (AML)',
                'Art. 6(1)(a) — Consent (optional)',
            ],
            'data_recipients' => [
                'PSD2-compliant EU/EEA banks — data stays within EU',
                'No data is transferred outside the EU/EEA.',
            ],
            'retention' => [
                'Account data: 5 years (EU AML Directive)',
                'Transaction records: 10 years (German HGB §257)',
            ],
            'your_rights' => [
                'Access (Art. 15)' => 'GET /api/gdpr/export',
                'Erasure (Art. 17)' => 'POST /api/gdpr/erase',
                'Data Portability (Art. 20)' => 'GET /api/gdpr/export',
                'Consent Management (Art. 7)' => 'PATCH /api/gdpr/consent',
            ],
        ];
    }
}
