package com.example.eupay.crypto

import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import java.security.*
import java.security.spec.X509EncodedKeySpec
import java.util.Base64
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.spec.GCMParameterSpec
import javax.crypto.spec.SecretKeySpec

/**
 * Manages the user's RSA-4096 keypair in Android Keystore.
 *
 * The private key is hardware-backed (when available) and NEVER exportable.
 * Only the public key leaves the device — sent to the backend so it can
 * encrypt data that only this device can decrypt.
 *
 * Uses envelope encryption:
 * - RSA-OAEP encrypts a random AES-256 key (the DEK)
 * - AES-256-GCM encrypts the actual data
 * - Combined blob: encrypted_dek(512) + iv(12) + tag(16) + ciphertext(n)
 */
object ClientKeyManager {

    private const val KEYSTORE_PROVIDER = "AndroidKeyStore"
    private const val KEY_ALIAS = "eupay_user_rsa_key"
    private const val RSA_KEY_SIZE = 4096
    private const val RSA_CIPHER = "RSA/ECB/OAEPWithSHA-256AndMGF1Padding"
    private const val AES_CIPHER = "AES/GCM/NoPadding"
    private const val GCM_TAG_BITS = 128
    private const val GCM_IV_BYTES = 12
    private const val AES_KEY_BYTES = 32 // 256 bits
    private const val RSA_ENCRYPTED_DEK_BYTES = 512 // 4096-bit RSA = 512-byte output

    /**
     * Generate (or retrieve existing) RSA-4096 keypair in Android Keystore.
     * Private key is non-exportable and hardware-backed when available.
     */
    fun getOrCreateKeyPair(): KeyPair {
        val keyStore = KeyStore.getInstance(KEYSTORE_PROVIDER).apply { load(null) }

        if (keyStore.containsAlias(KEY_ALIAS)) {
            val privateKey = keyStore.getKey(KEY_ALIAS, null) as PrivateKey
            val publicKey = keyStore.getCertificate(KEY_ALIAS).publicKey
            return KeyPair(publicKey, privateKey)
        }

        val spec = KeyGenParameterSpec.Builder(
            KEY_ALIAS,
            KeyProperties.PURPOSE_DECRYPT or KeyProperties.PURPOSE_ENCRYPT
        )
            .setKeySize(RSA_KEY_SIZE)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_RSA_OAEP)
            .setDigests(KeyProperties.DIGEST_SHA256)
            // Private key never leaves the Keystore
            .setUserAuthenticationRequired(false) // biometric handled at app level
            .build()

        val keyPairGenerator = KeyPairGenerator.getInstance(
            KeyProperties.KEY_ALGORITHM_RSA,
            KEYSTORE_PROVIDER
        )
        keyPairGenerator.initialize(spec)
        return keyPairGenerator.generateKeyPair()
    }

    /**
     * Export the public key as Base64-encoded DER (X.509 SubjectPublicKeyInfo).
     * This is what gets sent to the backend.
     */
    fun exportPublicKey(): String {
        val keyPair = getOrCreateKeyPair()
        return Base64.getEncoder().encodeToString(keyPair.public.encoded)
    }

    /**
     * Check if a keypair exists in the Keystore.
     */
    fun hasKeyPair(): Boolean {
        val keyStore = KeyStore.getInstance(KEYSTORE_PROVIDER).apply { load(null) }
        return keyStore.containsAlias(KEY_ALIAS)
    }

    /**
     * Delete the keypair (e.g., on account deletion).
     * WARNING: All data encrypted with this key becomes permanently unrecoverable.
     */
    fun deleteKeyPair() {
        val keyStore = KeyStore.getInstance(KEYSTORE_PROVIDER).apply { load(null) }
        if (keyStore.containsAlias(KEY_ALIAS)) {
            keyStore.deleteEntry(KEY_ALIAS)
        }
    }

    // ──────────────────────────────────────────────────────
    // Decryption (Android-side only — backend can't do this)
    // ──────────────────────────────────────────────────────

    /**
     * Decrypt an envelope-encrypted blob from the backend.
     *
     * Blob format: encrypted_dek(512) + iv(12) + ciphertext_with_tag(n)
     *
     * Steps:
     * 1. Extract encrypted DEK (first 512 bytes)
     * 2. Decrypt DEK with RSA private key from Keystore
     * 3. Extract IV (next 12 bytes)
     * 4. Decrypt ciphertext with AES-256-GCM using DEK
     */
    fun decrypt(encryptedBase64: String): String {
        val blob = Base64.getDecoder().decode(encryptedBase64)

        if (blob.size < RSA_ENCRYPTED_DEK_BYTES + GCM_IV_BYTES + GCM_TAG_BITS / 8) {
            throw IllegalArgumentException("Encrypted blob too short")
        }

        // 1. Extract encrypted DEK
        val encryptedDek = blob.copyOfRange(0, RSA_ENCRYPTED_DEK_BYTES)

        // 2. Decrypt DEK with RSA private key
        val keyPair = getOrCreateKeyPair()
        val rsaCipher = Cipher.getInstance(RSA_CIPHER)
        rsaCipher.init(Cipher.DECRYPT_MODE, keyPair.private)
        val dek = rsaCipher.doFinal(encryptedDek)

        // 3. Extract IV
        val iv = blob.copyOfRange(RSA_ENCRYPTED_DEK_BYTES, RSA_ENCRYPTED_DEK_BYTES + GCM_IV_BYTES)

        // 4. Decrypt ciphertext with AES-GCM
        val ciphertext = blob.copyOfRange(RSA_ENCRYPTED_DEK_BYTES + GCM_IV_BYTES, blob.size)
        val aesKey = SecretKeySpec(dek, "AES")
        val aesCipher = Cipher.getInstance(AES_CIPHER)
        aesCipher.init(Cipher.DECRYPT_MODE, aesKey, GCMParameterSpec(GCM_TAG_BITS, iv))
        val plaintext = aesCipher.doFinal(ciphertext)

        return String(plaintext, Charsets.UTF_8)
    }

    /**
     * Decrypt a map of encrypted fields from a backend response.
     * Returns a new map with decrypted values.
     */
    fun decryptFields(encryptedFields: Map<String, String?>): Map<String, String?> {
        return encryptedFields.mapValues { (_, value) ->
            if (value != null && value.isNotBlank()) {
                try {
                    decrypt(value)
                } catch (e: Exception) {
                    null // field couldn't be decrypted
                }
            } else {
                null
            }
        }
    }

    // ──────────────────────────────────────────────────────
    // Client-side encryption (for sending data to backend
    // that backend should store but not read)
    // ──────────────────────────────────────────────────────

    /**
     * Encrypt data locally using own public key (envelope encryption).
     * Useful for: client wants to store encrypted data on server
     * that only this client can read back.
     */
    fun encryptForSelf(plaintext: String): String {
        val keyPair = getOrCreateKeyPair()
        return encryptWithPublicKey(plaintext, keyPair.public)
    }

    /**
     * Encrypt using a specific public key (e.g., for key recovery flows).
     */
    fun encryptWithPublicKey(plaintext: String, publicKey: PublicKey): String {
        // 1. Generate random AES-256 DEK
        val dekGenerator = KeyGenerator.getInstance("AES")
        dekGenerator.init(AES_KEY_BYTES * 8)
        val dek = dekGenerator.generateKey()

        // 2. Encrypt DEK with RSA public key
        val rsaCipher = Cipher.getInstance(RSA_CIPHER)
        rsaCipher.init(Cipher.ENCRYPT_MODE, publicKey)
        val encryptedDek = rsaCipher.doFinal(dek.encoded)

        // 3. Encrypt plaintext with AES-256-GCM
        val iv = ByteArray(GCM_IV_BYTES).also { SecureRandom().nextBytes(it) }
        val aesCipher = Cipher.getInstance(AES_CIPHER)
        aesCipher.init(Cipher.ENCRYPT_MODE, dek, GCMParameterSpec(GCM_TAG_BITS, iv))
        val ciphertext = aesCipher.doFinal(plaintext.toByteArray(Charsets.UTF_8))

        // 4. Combine: encrypted_dek + iv + ciphertext (includes GCM tag)
        val blob = encryptedDek + iv + ciphertext

        return Base64.getEncoder().encodeToString(blob)
    }

    /**
     * Import a public key from Base64-encoded DER format.
     */
    fun importPublicKey(base64Der: String): PublicKey {
        val keyBytes = Base64.getDecoder().decode(base64Der)
        val keySpec = X509EncodedKeySpec(keyBytes)
        val keyFactory = KeyFactory.getInstance("RSA")
        return keyFactory.generatePublic(keySpec)
    }
}
