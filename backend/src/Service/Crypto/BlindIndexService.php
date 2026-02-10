<?php

declare(strict_types=1);

namespace App\Service\Crypto;

/**
 * Generates deterministic, one-way blind indexes for encrypted fields.
 *
 * Problem: if email is encrypted with the user's public key, the backend
 * can't search for it during login. Solution: store a blind index alongside.
 *
 * blind_index = HMAC-SHA256(server_key, normalize(value))
 *
 * Properties:
 * - Deterministic: same input always produces same index (enables lookup)
 * - One-way: cannot reverse HMAC to recover the email
 * - Keyed: without server_key, can't compute indexes (prevents rainbow tables)
 * - Normalized: case-insensitive, trimmed (email "A@B.COM" == "a@b.com")
 *
 * We truncate to 32 bytes (hex 64 chars) — collision probability is negligible
 * for any realistic user count (birthday bound: ~2^128 for 256-bit HMAC).
 */
class BlindIndexService
{
    private readonly string $key;

    public function __construct(string $blindIndexKey)
    {
        if (strlen($blindIndexKey) < 32) {
            throw new \InvalidArgumentException(
                'Blind index key must be at least 32 characters for security'
            );
        }
        $this->key = $blindIndexKey;
    }

    /**
     * Compute a blind index for an email address.
     * Normalized: lowercased + trimmed.
     */
    public function indexEmail(string $email): string
    {
        return $this->compute(strtolower(trim($email)));
    }

    /**
     * Compute a blind index for an IBAN.
     * Normalized: uppercased, whitespace stripped.
     */
    public function indexIban(string $iban): string
    {
        return $this->compute(strtoupper(preg_replace('/\s+/', '', $iban)));
    }

    /**
     * Compute a blind index for a phone number.
     * Normalized: digits and + only.
     */
    public function indexPhone(string $phone): string
    {
        return $this->compute(preg_replace('/[^+0-9]/', '', $phone));
    }

    /**
     * Generic blind index computation.
     */
    public function compute(string $normalizedValue): string
    {
        return hash_hmac('sha256', $normalizedValue, $this->key);
    }

    /**
     * Verify a value against a stored blind index.
     */
    public function verify(string $normalizedValue, string $storedIndex): bool
    {
        return hash_equals($storedIndex, $this->compute($normalizedValue));
    }
}
