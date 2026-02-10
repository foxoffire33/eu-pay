<?php
declare(strict_types=1);
namespace App\Service;

/** AES-256-GCM encryption for card tokens at rest. */
class CardEncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LEN = 16;

    public function __construct(private readonly string $encryptionKey) {}

    public function encrypt(string $plaintext): string
    {
        $key = hex2bin($this->encryptionKey);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        return base64_encode($iv . $tag . $ct);
    }

    public function decrypt(string $encoded): string
    {
        $key = hex2bin($this->encryptionKey);
        $raw = base64_decode($encoded);
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, self::TAG_LEN);
        $ct  = substr($raw, 12 + self::TAG_LEN);
        $pt  = openssl_decrypt($ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) throw new \RuntimeException('Decryption failed');
        return $pt;
    }
}
