package nl.delaparra_services.apps.eupay.util

import android.annotation.SuppressLint
import android.content.Context
import android.os.Build
import android.provider.Settings
import java.security.MessageDigest

/**
 * Generates a stable device fingerprint for HCE token binding.
 *
 * EU Compliance:
 * - ePrivacy Directive Art. 5(3): accessing device identifiers requires informed consent
 * - GDPR Art. 6(1)(a): processing based on consent
 * - The app MUST obtain explicit user consent before calling [generate]
 * - Consent status is tracked via [ConsentManager] and the backend
 *
 * The fingerprint is a one-way SHA-256 hash — the original device identifiers
 * cannot be reconstructed (data minimization per GDPR Art. 5(1)(c)).
 */
object DeviceFingerprint {

    class ConsentRequiredException :
        IllegalStateException("Device fingerprinting requires explicit user consent under ePrivacy Directive Art. 5(3)")

    /**
     * Generate a device fingerprint. Caller MUST verify consent first.
     *
     * @param context Application context
     * @param consentGiven Must be true — caller asserts user has given device tracking consent
     * @throws ConsentRequiredException if consentGiven is false
     */
    @SuppressLint("HardwareIds")
    fun generate(context: Context, consentGiven: Boolean): String {
        if (!consentGiven) {
            throw ConsentRequiredException()
        }

        val androidId = Settings.Secure.getString(
            context.contentResolver,
            Settings.Secure.ANDROID_ID
        ) ?: "unknown"

        // Use only minimal hardware identifiers needed for security binding
        val raw = buildString {
            append(androidId)
            append("|")
            append(Build.MANUFACTURER)
            append("|")
            append(Build.MODEL)
        }

        return sha256(raw)
    }

    /**
     * Generate a privacy-preserving fingerprint without device identifiers.
     * Uses only a random installation ID — no consent required.
     * Less stable across reinstalls but fully privacy-compliant.
     */
    fun generatePrivacyPreserving(context: Context): String {
        val prefs = context.getSharedPreferences("eupay_install", Context.MODE_PRIVATE)
        var installId = prefs.getString("install_id", null)
        if (installId == null) {
            installId = java.util.UUID.randomUUID().toString()
            prefs.edit().putString("install_id", installId).apply()
        }
        return sha256(installId)
    }

    fun sha256(input: String): String {
        val digest = MessageDigest.getInstance("SHA-256")
        val hash = digest.digest(input.toByteArray(Charsets.UTF_8))
        return hash.joinToString("") { "%02x".format(it) }
    }
}
