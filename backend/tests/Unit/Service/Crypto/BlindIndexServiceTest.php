<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Crypto;

use App\Service\Crypto\BlindIndexService;
use PHPUnit\Framework\TestCase;

class BlindIndexServiceTest extends TestCase
{
    private BlindIndexService $service;

    protected function setUp(): void
    {
        $this->service = new BlindIndexService(
            'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789'
        );
    }

    public function testEmailIndexIsDeterministic(): void
    {
        $a = $this->service->indexEmail('max@example.com');
        $b = $this->service->indexEmail('max@example.com');
        $this->assertEquals($a, $b);
    }

    public function testEmailIndexIsCaseInsensitive(): void
    {
        $lower = $this->service->indexEmail('max@example.com');
        $upper = $this->service->indexEmail('MAX@EXAMPLE.COM');
        $mixed = $this->service->indexEmail('Max@Example.Com');
        $this->assertEquals($lower, $upper);
        $this->assertEquals($lower, $mixed);
    }

    public function testEmailIndexTrimmed(): void
    {
        $normal = $this->service->indexEmail('max@example.com');
        $padded = $this->service->indexEmail('  max@example.com  ');
        $this->assertEquals($normal, $padded);
    }

    public function testDifferentEmailsDifferentIndexes(): void
    {
        $a = $this->service->indexEmail('a@example.com');
        $b = $this->service->indexEmail('b@example.com');
        $this->assertNotEquals($a, $b);
    }

    public function testIndexIs64HexChars(): void
    {
        $idx = $this->service->indexEmail('max@example.com');
        $this->assertEquals(64, strlen($idx));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $idx);
    }

    public function testIbanIndexNormalizesWhitespace(): void
    {
        $compact = $this->service->indexIban('DE89370400440532013000');
        $spaced = $this->service->indexIban('DE89 3704 0044 0532 0130 00');
        $this->assertEquals($compact, $spaced);
    }

    public function testIbanIndexUppercased(): void
    {
        $upper = $this->service->indexIban('DE89370400440532013000');
        $lower = $this->service->indexIban('de89370400440532013000');
        $this->assertEquals($upper, $lower);
    }

    public function testPhoneIndexStripsFormatting(): void
    {
        $clean = $this->service->indexPhone('+491234567890');
        $formatted = $this->service->indexPhone('+49 123 456 7890');
        $dashed = $this->service->indexPhone('+49-123-456-7890');
        $this->assertEquals($clean, $formatted);
        $this->assertEquals($clean, $dashed);
    }

    public function testVerifyMatchesComputed(): void
    {
        $idx = $this->service->indexEmail('max@example.com');
        $this->assertTrue($this->service->verify('max@example.com', $idx));
        $this->assertFalse($this->service->verify('other@example.com', $idx));
    }

    public function testDifferentKeysProduceDifferentIndexes(): void
    {
        $service2 = new BlindIndexService(
            '0000000000000000000000000000000000000000000000000000000000000000'
        );
        $a = $this->service->indexEmail('max@example.com');
        $b = $service2->indexEmail('max@example.com');
        $this->assertNotEquals($a, $b);
    }

    public function testShortKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BlindIndexService('too_short');
    }
}
