<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Crypto;

use App\Service\Crypto\EnvelopeEncryptionService;
use PHPUnit\Framework\TestCase;

class EnvelopeEncryptionServiceTest extends TestCase
{
    private EnvelopeEncryptionService $service;
    private string $publicKeyPem;
    private \OpenSSLAsymmetricKey $privateKey;

    protected function setUp(): void
    {
        $this->service = new EnvelopeEncryptionService();

        // Generate a real RSA-2048 keypair for testing (4096 is too slow for tests)
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        $this->assertNotFalse($res, 'Failed to generate RSA keypair');

        $details = openssl_pkey_get_details($res);
        $this->publicKeyPem = $details['key'];
        $this->privateKey = $res;
    }

    public function testEncryptProducesBase64Output(): void
    {
        $encrypted = $this->service->encrypt('hello world', $this->publicKeyPem);
        $this->assertNotEmpty($encrypted);
        // Must be valid base64
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);
    }

    public function testEncryptedDataIsLargerThanInput(): void
    {
        $encrypted = $this->service->encrypt('short', $this->publicKeyPem);
        $blob = base64_decode($encrypted);
        // RSA-2048 encrypted DEK = 256 bytes + IV (12) + tag (16) + ciphertext
        $this->assertGreaterThan(256 + 12 + 16, strlen($blob));
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $a = $this->service->encrypt('same input', $this->publicKeyPem);
        $b = $this->service->encrypt('same input', $this->publicKeyPem);
        // Each encryption uses a random DEK and IV, so outputs differ
        $this->assertNotEquals($a, $b);
    }

    public function testDecryptWithPrivateKey(): void
    {
        $plaintext = 'max@example.com';
        $encrypted = $this->service->encrypt($plaintext, $this->publicKeyPem);

        // Decrypt manually (simulating what Android does)
        $blob = base64_decode($encrypted);
        $keyBits = 2048;
        $dekSize = $keyBits / 8; // 256 bytes for RSA-2048

        // Extract encrypted DEK
        $encryptedDek = substr($blob, 0, $dekSize);
        openssl_private_decrypt($encryptedDek, $dek, $this->privateKey, OPENSSL_PKCS1_OAEP_PADDING);

        // Extract IV and ciphertext
        $iv = substr($blob, $dekSize, 12);
        $tag = substr($blob, $dekSize + 12, 16);
        $ciphertext = substr($blob, $dekSize + 12 + 16);

        // Decrypt with AES-256-GCM
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $dek, OPENSSL_RAW_DATA, $iv, $tag);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptFieldsMultiple(): void
    {
        $fields = [
            'email' => 'max@example.com',
            'first_name' => 'Max',
            'phone' => null,
        ];

        $encrypted = $this->service->encryptFields($fields, $this->publicKeyPem);

        $this->assertNotEmpty($encrypted['email']);
        $this->assertNotEmpty($encrypted['first_name']);
        $this->assertNull($encrypted['phone']); // null stays null
    }

    public function testEncryptEmptyStringTreatedAsNull(): void
    {
        $encrypted = $this->service->encryptFields(['x' => ''], $this->publicKeyPem);
        $this->assertNull($encrypted['x']);
    }

    public function testValidatePublicKeyAcceptsValid(): void
    {
        $this->assertTrue($this->service->validatePublicKey($this->publicKeyPem));
    }

    public function testValidatePublicKeyRejectsGarbage(): void
    {
        $this->assertFalse($this->service->validatePublicKey('not a key'));
    }

    public function testValidatePublicKeyRejectsShortKey(): void
    {
        // Generate a 1024-bit key (below minimum)
        $short = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($short);
        $this->assertFalse($this->service->validatePublicKey($details['key']));
    }

    public function testGetKeyBits(): void
    {
        $bits = $this->service->getKeyBits($this->publicKeyPem);
        $this->assertEquals(2048, $bits);
    }

    public function testAcceptsBase64DerFormat(): void
    {
        // Extract base64 DER from PEM
        $lines = explode("\n", $this->publicKeyPem);
        $base64Der = implode('', array_filter($lines, fn($l) => !str_starts_with($l, '-----')));

        $this->assertTrue($this->service->validatePublicKey($base64Der));
        // Should also encrypt successfully
        $encrypted = $this->service->encrypt('test', $base64Der);
        $this->assertNotEmpty($encrypted);
    }

    public function testInvalidKeyThrowsOnEncrypt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->encrypt('test', 'not a valid key');
    }
}
