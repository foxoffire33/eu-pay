package nl.delaparra_services.apps.eupay.service

import android.app.Activity
import androidx.credentials.CreatePublicKeyCredentialRequest
import androidx.credentials.CredentialManager
import androidx.credentials.GetCredentialRequest
import androidx.credentials.GetPublicKeyCredentialOption

/**
 * Wraps Android Credential Manager for WebAuthn passkey operations.
 *
 * Supports both platform passkeys (biometric) and cross-platform authenticators
 * (USB/NFC security keys like YubiKey). When authenticatorAttachment is null in
 * the server options, the system shows choices for both.
 */
class PasskeyService(
    private val credentialManager: CredentialManager,
) {
    /**
     * Create a new passkey (registration / attestation).
     * Shows system UI for biometric or security key selection.
     *
     * @param optionsJson The PublicKeyCredentialCreationOptions JSON from the server
     * @param activity The activity context for the system UI
     * @return The credential JSON response to send back to the server
     */
    suspend fun createPasskey(optionsJson: String, activity: Activity): String {
        val request = CreatePublicKeyCredentialRequest(
            requestJson = optionsJson,
        )
        val result = credentialManager.createCredential(activity, request)
        return result.data.getString("androidx.credentials.BUNDLE_KEY_REGISTRATION_RESPONSE_JSON")
            ?: throw IllegalStateException("No registration response in credential result")
    }

    /**
     * Authenticate with an existing passkey (login / assertion).
     * Shows system UI for passkey selection (platform or security key).
     *
     * @param optionsJson The PublicKeyCredentialRequestOptions JSON from the server
     * @param activity The activity context for the system UI
     * @return The credential JSON response to send back to the server
     */
    suspend fun getPasskey(optionsJson: String, activity: Activity): String {
        val option = GetPublicKeyCredentialOption(
            requestJson = optionsJson,
        )
        val request = GetCredentialRequest(listOf(option))
        val result = credentialManager.getCredential(activity, request)
        return result.credential.data.getString("androidx.credentials.BUNDLE_KEY_AUTHENTICATION_RESPONSE_JSON")
            ?: throw IllegalStateException("No authentication response in credential result")
    }
}
