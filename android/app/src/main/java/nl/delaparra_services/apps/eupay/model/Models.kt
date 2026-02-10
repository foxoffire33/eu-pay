package nl.delaparra_services.apps.eupay.model

import com.google.gson.annotations.SerializedName

// ── Auth (Passkey / WebAuthn) ────────────────────

data class PasskeyRegisterOptionsRequest(
    @SerializedName("display_name") val displayName: String,
    @SerializedName("gdpr_consent") val gdprConsent: Boolean = false,
    @SerializedName("privacy_policy_version") val privacyPolicyVersion: String = "1.0",
    @SerializedName("encrypted_email") val encryptedEmail: String? = null,
    @SerializedName("encrypted_first_name") val encryptedFirstName: String? = null,
    @SerializedName("encrypted_last_name") val encryptedLastName: String? = null,
    @SerializedName("public_key") val publicKey: String? = null,
)

data class PasskeyOptionsResponse(
    @SerializedName("challenge_token") val challengeToken: String,
    val options: Map<String, Any>,
)

data class PasskeyRegisterRequest(
    @SerializedName("challenge_token") val challengeToken: String,
    val credential: Map<String, Any>,
)

data class PasskeyRegisterResponse(
    val token: String,
    @SerializedName("user_id") val userId: String,
)

data class PasskeyLoginRequest(
    @SerializedName("challenge_token") val challengeToken: String,
    val credential: Map<String, Any>,
)

data class PasskeyLoginResponse(
    val token: String,
    @SerializedName("user_id") val userId: String,
)

data class AuthResponse(
    val token: String,
    @SerializedName("user_id") val userId: String?,
)

/**
 * Backend returns encrypted blobs — client decrypts locally.
 */
data class UserProfile(
    val id: String,
    @SerializedName("display_name") val displayName: String?,
    @SerializedName("encrypted_email") val encryptedEmail: String?,
    @SerializedName("encrypted_first_name") val encryptedFirstName: String?,
    @SerializedName("encrypted_last_name") val encryptedLastName: String?,
    @SerializedName("public_key") val publicKey: String?,
    @SerializedName("has_bank_account") val hasBankAccount: Boolean?,
    @SerializedName("created_at") val createdAt: String?,
    @SerializedName("gdpr_consent_at") val gdprConsentAt: String?,
)

// ── Key Rotation ────────────────────────────────

data class KeyRotateRequest(
    @SerializedName("public_key") val publicKey: String,
    @SerializedName("re_encrypted_email") val reEncryptedEmail: String?,
    @SerializedName("re_encrypted_first_name") val reEncryptedFirstName: String?,
    @SerializedName("re_encrypted_last_name") val reEncryptedLastName: String?,
    @SerializedName("re_encrypted_phone") val reEncryptedPhone: String?,
)

// ── Account ─────────────────────────────────────

data class AccountResponse(
    val consentId: String?,
    val authorisationUrl: String?,
    val validUntil: String?,
)

data class BalanceResponse(
    val balances: List<Map<String, Any>>?,
    @SerializedName("account_id") val accountId: String?,
)

// ── Cards ───────────────────────────────────────

data class CardResponse(
    val id: String,
    val type: String,
    val scheme: String,
    val status: String,
    @SerializedName("last_four") val lastFour: String?,
    @SerializedName("expiry_date") val expiryDate: String?,
    @SerializedName("created_at") val createdAt: String?
) {
    val isActive: Boolean get() = status == "ACTIVE"
    val displayName: String get() = "$scheme •••• ${lastFour ?: "????"}"
}

// ── HCE Tokens ──────────────────────────────────

data class HceProvisionRequest(
    @SerializedName("card_id") val cardId: String,
    @SerializedName("device_fingerprint") val deviceFingerprint: String
)

data class HceProvisionResponse(
    @SerializedName("token_id") val tokenId: String,
    val status: String,
    @SerializedName("expires_at") val expiresAt: String
)

data class HcePaymentPayload(
    @SerializedName("token_id") val tokenId: String,
    val dpan: String,
    @SerializedName("expiry_month") val expiryMonth: Int,
    @SerializedName("expiry_year") val expiryYear: Int,
    @SerializedName("session_key") val sessionKey: String,
    val atc: Int,
    @SerializedName("card_scheme") val cardScheme: String,
    val aid: String,
    @SerializedName("expires_at") val expiresAt: String
)

data class HceRefreshResponse(
    @SerializedName("session_key") val sessionKey: String,
    @SerializedName("expires_at") val expiresAt: String,
    val atc: Int
)

data class HceTokenInfo(
    @SerializedName("token_id") val tokenId: String,
    @SerializedName("card_id") val cardId: String,
    @SerializedName("card_scheme") val cardScheme: String,
    @SerializedName("device_fingerprint") val deviceFingerprint: String,
    val status: String,
    val atc: Int,
    @SerializedName("expires_at") val expiresAt: String
)

// ── Transactions ────────────────────────────────

data class TransactionsWrapper(
    val transactions: List<TransactionResponse>,
)

data class TransactionResponse(
    val id: String,
    val type: String,
    val status: String,
    val amount: String,
    val currency: String,
    @SerializedName("encrypted_merchant_name") val encryptedMerchantName: String?,
    @SerializedName("pos_entry_mode") val posEntryMode: String?,
    @SerializedName("created_at") val createdAt: String
)

// ── Errors ──────────────────────────────────────

data class ApiError(
    val error: String?,
    val detail: String? = null
)

// ── GDPR Consent (EU compliance) ────────────────

data class ConsentResponse(
    @SerializedName("gdpr_consent") val gdprConsent: Boolean,
    @SerializedName("gdpr_consent_at") val gdprConsentAt: String?,
    @SerializedName("privacy_policy_version") val privacyPolicyVersion: String?,
    @SerializedName("device_tracking_consent") val deviceTrackingConsent: Boolean,
    @SerializedName("marketing_consent") val marketingConsent: Boolean,
    @SerializedName("legal_basis") val legalBasis: String?,
)

data class ConsentUpdateRequest(
    @SerializedName("device_tracking_consent") val deviceTrackingConsent: Boolean? = null,
    @SerializedName("marketing_consent") val marketingConsent: Boolean? = null,
)

data class ConsentUpdateResponse(
    @SerializedName("device_tracking_consent") val deviceTrackingConsent: Boolean,
    @SerializedName("marketing_consent") val marketingConsent: Boolean,
    @SerializedName("updated_at") val updatedAt: String?,
)

data class EraseRequest(
    @SerializedName("confirm_deletion") val confirmDeletion: Boolean
)

data class EraseResponse(
    val status: String,
    val message: String
)

// Top-Up and P2P models are defined in their respective service files:
// TopUpService.kt and P2PService.kt
