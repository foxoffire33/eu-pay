package com.example.eupay.model

import com.google.gson.annotations.SerializedName

// ── Auth ────────────────────────────────────────

data class RegisterRequest(
    val email: String,
    val password: String,
    @SerializedName("first_name") val firstName: String,
    @SerializedName("last_name") val lastName: String,
    @SerializedName("phone_number") val phoneNumber: String? = null,
    @SerializedName("birth_date") val birthDate: String? = null,
    val nationality: String = "DE",
    val address: Address? = null,
    // RSA-4096 public key (Base64 DER) — backend encrypts PII with this
    @SerializedName("public_key") val publicKey: String,
    // ── GDPR Consent (mandatory under Art. 6/7) ──
    @SerializedName("gdpr_consent") val gdprConsent: Boolean = false,
    @SerializedName("device_tracking_consent") val deviceTrackingConsent: Boolean = false,
    @SerializedName("marketing_consent") val marketingConsent: Boolean = false,
)

data class Address(
    val line1: String,
    val city: String,
    @SerializedName("postal_code") val postalCode: String,
    val country: String = "DE"
)

data class LoginRequest(
    val email: String,
    val password: String
)

data class AuthResponse(
    val token: String
)

/**
 * Backend returns encrypted blobs — client decrypts locally.
 */
data class UserProfile(
    val id: String,
    @SerializedName("kyc_status") val kycStatus: String,
    @SerializedName("has_account") val hasAccount: Boolean,
    @SerializedName("encrypted_fields") val encryptedFields: EncryptedUserFields?,
    val consent: ConsentInfo?,
    @SerializedName("public_key_fingerprint") val publicKeyFingerprint: String?,
)

data class EncryptedUserFields(
    val email: String?,
    @SerializedName("first_name") val firstName: String?,
    @SerializedName("last_name") val lastName: String?,
    @SerializedName("phone_number") val phoneNumber: String?,
    val iban: String?,
)

data class ConsentInfo(
    val gdpr: Boolean,
    @SerializedName("privacy_version") val privacyVersion: String?,
    @SerializedName("device_tracking") val deviceTracking: Boolean,
    val marketing: Boolean,
)

/** Registration response — no plaintext PII returned */
data class RegisterResponse(
    val id: String,
    @SerializedName("kyc_status") val kycStatus: String,
    @SerializedName("public_key_fingerprint") val publicKeyFingerprint: String?,
    val encryption: String?, // "zero_knowledge"
)

// ── Key Rotation ────────────────────────────────

data class KeyRotateRequest(
    @SerializedName("new_public_key") val newPublicKey: String,
    val fields: KeyRotateFields,
)

data class KeyRotateFields(
    val email: String,
    @SerializedName("first_name") val firstName: String,
    @SerializedName("last_name") val lastName: String,
    @SerializedName("phone_number") val phoneNumber: String? = null,
    val iban: String? = null,
)

// ── Account ─────────────────────────────────────

data class AccountResponse(
    @SerializedName("account_id") val accountId: String,
    @SerializedName("encrypted_iban") val encryptedIban: String?, // client decrypts
)

data class BalanceResponse(
    val balance: MoneyAmount?,
    @SerializedName("available_balance") val availableBalance: MoneyAmount?
)

data class MoneyAmount(
    val value: Double,
    val currency: String = "EUR"
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

data class EraseRequest(
    @SerializedName("confirm_deletion") val confirmDeletion: Boolean
)

data class EraseResponse(
    val status: String,
    val message: String
)
