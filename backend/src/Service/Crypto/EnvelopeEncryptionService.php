<?php

declare(strict_types=1);

namespace App\Service\Crypto;

/**
 * Envelope encryption using the user's RSA public key.
 *
 * The backend can ENCRYPT data with the user's public key,
 * but can NEVER decrypt it. Only the Android app with the
 * private key in Android Keystore can decrypt.
 *
 * Envelope format (binary, then Base64-encoded):
 *   encrypted_dek (512 bytes for RSA-4096)
 * + iv (12 bytes for AES-GCM)
 * + ciphertext with GCM tag (n + 16 bytes)
 *
 * Steps:
 * 1. Generate random AES-256 key (DEK - Data Encryption Key)
 * 2. Encrypt plaintext with AES-256-GCM using DEK
 * 3. Encrypt DEK with user's RSA public key (RSA-OAEP SHA-256)
 * 4. Concatenate and Base64-encode
 * 5. Discard plaintext DEK — backend cannot recover it
 */
class EnvelopeEncryptionService
{
    private const AES_CIPHER = 'aes-256-gcm';
    private const AES_KEY_BYTES = 32;
    private const GCM_IV_BYTES = 12;
    private const GCM_TAG_BYTES = 16;
    private const RSA_PADDING = OPENSSL_PKCS1_OAEP_PADDING;

    /**
     * Encrypt plaintext using the user's RSA public key.
     *
     * @param plaintext The data to encrypt
     * @param publicKeyPem The user's RSA public key in PEM or DER-Base64 format
     * @return string Base64-encoded envelope: encrypted_dek + iv + ciphertext_with_tag
     */
    public function encrypt(string $plaintext, string $publicKeyPem): string
    {
        $publicKey = $this->loadPublicKey($publicKeyPem);

        // 1. Generate random AES-256 DEK
        $dek = random_bytes(self::AES_KEY_BYTES);

        // 2. Encrypt plaintext with AES-256-GCM
        $iv = random_bytes(self::GCM_IV_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::AES_CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::GCM_TAG_BYTES,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('AES-GCM encryption failed');
        }

        // 3. Encrypt DEK with user's RSA public key
        $encryptedDek = '';
        $success = openssl_public_encrypt($dek, $encryptedDek, $publicKey, self::RSA_PADDING);
        if (!$success) {
            throw new \RuntimeException('RSA encryption of DEK failed: ' . openssl_error_string());
        }

        // 4. Zero out the plaintext DEK — we never need it again
        sodium_memzero($dek);

        // 5. Combine: encrypted_dek + iv + tag + ciphertext
        $envelope = $encryptedDek . $iv . $tag . $ciphertext;

        return base64_encode($envelope);
    }

    /**
     * Encrypt multiple fields at once.
     *
     * @param fields Map of field_name => plaintext_value
     * @param publicKeyPem User's RSA public key
     * @return array Map of field_name => encrypted_value
     */
    public function encryptFields(array $fields, string $publicKeyPem): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') {
                $result[$key] = $this->encrypt($value, $publicKeyPem);
            } else {
                $result[$key] = null;
            }
        }
        return $result;
    }

    /**
     * Verify that a public key is a valid RSA key of sufficient strength.
     */
    public function validatePublicKey(string $publicKeyPem): bool
    {
        try {
            $key = $this->loadPublicKey($publicKeyPem);
            $details = openssl_pkey_get_details($key);
            if ($details === false) {
                return false;
            }
            // Require at least RSA-2048 (we recommend 4096)
            return ($details['type'] === OPENSSL_KEYTYPE_RSA) && ($details['bits'] >= 2048);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the key size in bits.
     */
    public function getKeyBits(string $publicKeyPem): int
    {
        $key = $this->loadPublicKey($publicKeyPem);
        $details = openssl_pkey_get_details($key);
        return $details['bits'] ?? 0;
    }

    /**
     * Load a public key from PEM string or Base64-encoded DER.
     */
    private function loadPublicKey(string $input): \OpenSSLAsymmetricKey
    {
        // Try as PEM first
        $key = openssl_pkey_get_public($input);
        if ($key !== false) {
            return $key;
        }

        // Try wrapping Base64 DER in PEM headers
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($input, 64, "\n")
            . "-----END PUBLIC KEY-----";

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \InvalidArgumentException(
                'Invalid RSA public key. Provide PEM or Base64-encoded DER format.'
            );
        }

        return $key;
    }
}
