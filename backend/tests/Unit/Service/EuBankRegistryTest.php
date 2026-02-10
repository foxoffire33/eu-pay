<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\EuBankRegistry;
use PHPUnit\Framework\TestCase;

class EuBankRegistryTest extends TestCase
{
    public function testCoversAllEuEeaCountries(): void
    {
        $countries = EuBankRegistry::getSupportedCountries();

        // 27 EU member states
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];

        // EEA (non-EU)
        $eeaCountries = ['NO', 'IS', 'LI'];

        foreach ($euCountries as $cc) {
            $this->assertContains($cc, $countries, "Missing EU country: {$cc}");
        }

        foreach ($eeaCountries as $cc) {
            $this->assertContains($cc, $countries, "Missing EEA country: {$cc}");
        }
    }

    public function testHasMinimumBankCount(): void
    {
        $banks = EuBankRegistry::getAll();
        // At least 100 banks across EU/EEA
        $this->assertGreaterThan(100, count($banks));
    }

    public function testAllBanksHaveRequiredFields(): void
    {
        foreach (EuBankRegistry::getAll() as $bank) {
            $this->assertArrayHasKey('bic', $bank);
            $this->assertArrayHasKey('name', $bank);
            $this->assertArrayHasKey('country', $bank);
            $this->assertArrayHasKey('country_name', $bank);
            $this->assertNotEmpty($bank['bic']);
            $this->assertNotEmpty($bank['name']);
            $this->assertSame(2, strlen($bank['country']), "Country code should be 2 chars: {$bank['name']}");
        }
    }

    public function testFindRabobank(): void
    {
        $bank = EuBankRegistry::findByBic('RABONL2U');
        $this->assertNotNull($bank);
        $this->assertSame('Rabobank', $bank['name']);
        $this->assertSame('NL', $bank['country']);
    }

    public function testFindDeutscheBank(): void
    {
        $bank = EuBankRegistry::findByBic('DEUTDEFF');
        $this->assertNotNull($bank);
        $this->assertSame('Deutsche Bank', $bank['name']);
        $this->assertSame('DE', $bank['country']);
    }

    public function testFindBnpParibas(): void
    {
        $bank = EuBankRegistry::findByBic('BNPAFRPP');
        $this->assertNotNull($bank);
        $this->assertStringContainsString('BNP', $bank['name']);
        $this->assertSame('FR', $bank['country']);
    }

    public function testFilterByCountry(): void
    {
        $nlBanks = EuBankRegistry::getByCountry('NL');
        $this->assertGreaterThanOrEqual(7, count($nlBanks));

        foreach ($nlBanks as $bank) {
            $this->assertSame('NL', $bank['country']);
        }
    }

    public function testUnknownBicReturnsNull(): void
    {
        $this->assertNull(EuBankRegistry::findByBic('XXXX_NOPE'));
    }

    public function testCaseInsensitiveBicLookup(): void
    {
        $bank = EuBankRegistry::findByBic('rabonl2u');
        $this->assertNotNull($bank);
        $this->assertSame('Rabobank', $bank['name']);
    }
}
