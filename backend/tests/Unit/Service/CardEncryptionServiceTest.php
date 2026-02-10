<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CardEncryptionService;
use PHPUnit\Framework\TestCase;

class CardEncryptionServiceTest extends TestCase
{
    private CardEncryptionService $service;

    protected function setUp(): void
    {
        // 64 hex chars = 256 bits
        $this->service = new CardEncryptionService(
            '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
        );
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = '4000123456789012';
        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptedValueDiffersFromPlaintext(): void
    {
        $plaintext = '4000123456789012';
        $encrypted = $this->service->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
    }

    public function testEncryptionIsNonDeterministic(): void
    {
        $plaintext = 'same-input';
        $a = $this->service->encrypt($plaintext);
        $b = $this->service->encrypt($plaintext);

        // Due to random IV, encryptions of same plaintext differ
        $this->assertNotEquals($a, $b);
    }

    public function testBothEncryptionsDecryptToSameValue(): void
    {
        $plaintext = 'same-input';
        $a = $this->service->encrypt($plaintext);
        $b = $this->service->encrypt($plaintext);

        $this->assertEquals($plaintext, $this->service->decrypt($a));
        $this->assertEquals($plaintext, $this->service->decrypt($b));
    }

    public function testDecryptWithTamperedDataThrows(): void
    {
        $encrypted = $this->service->encrypt('secret');
        $raw = base64_decode($encrypted);
        // Flip a byte in the ciphertext
        $raw[20] = chr(ord($raw[20]) ^ 0xFF);
        $tampered = base64_encode($raw);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        $this->service->decrypt($tampered);
    }

    public function testDecryptWithInvalidBase64Throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->decrypt('not-valid-base64!!!');
    }

    public function testDecryptWithTooShortDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->decrypt(base64_encode('short'));
    }

    public function testInvalidKeyLengthThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('64 hex chars');
        new CardEncryptionService('tooshort');
    }

    public function testNonHexKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CardEncryptionService(str_repeat('zz', 32));
    }

    public function testEncryptEmptyString(): void
    {
        $encrypted = $this->service->encrypt('');
        $this->assertEquals('', $this->service->decrypt($encrypted));
    }

    public function testEncryptLongData(): void
    {
        $longString = str_repeat('A', 10000);
        $encrypted = $this->service->encrypt($longString);
        $this->assertEquals($longString, $this->service->decrypt($encrypted));
    }

    public function testDifferentKeysCannotDecrypt(): void
    {
        $otherService = new CardEncryptionService(
            'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'
        );

        $encrypted = $this->service->encrypt('secret');

        $this->expectException(\RuntimeException::class);
        $otherService->decrypt($encrypted);
    }
}
