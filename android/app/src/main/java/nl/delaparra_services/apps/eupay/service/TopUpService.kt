package nl.delaparra_services.apps.eupay.service

import com.google.gson.annotations.SerializedName
import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.*
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Top-up service — fund EU Pay account from any EU/EEA bank.
 *
 * Phone payment flow:
 *  1. User selects amount + bank (or enters IBAN)
 *  2. App calls backend → gets authorisation URL
 *  3. Opens bank's SCA page in Custom Chrome Tab
 *  4. User authenticates at bank (biometric / PIN / card reader)
 *  5. Bank redirects back → app polls for confirmation
 *  6. Funds credited to bank account
 *
 * Supports iDEAL (NL instant) + SEPA Credit Transfer (all EU/EEA).
 */
@Singleton
class TopUpService @Inject constructor(
    private val api: EuPayApi,
) {
    /**
     * Initiate iDEAL top-up (Dutch instant bank transfer).
     * Returns authorisation URL to open in browser for SCA.
     */
    suspend fun initiateIdeal(amountCents: Int, sourceBic: String? = null): TopUpResult {
        val response = api.initiateIdealTopUp(
            IdealTopUpRequest(amountCents = amountCents, sourceBic = sourceBic)
        )
        return TopUpResult(
            topUpId = response.topUpId,
            authorisationUrl = response.authorisationUrl,
            reference = response.reference,
        )
    }

    /**
     * Initiate SEPA Credit Transfer top-up (any EU/EEA bank).
     * PSD2 mandatory — works with ALL EU-licensed banks.
     */
    suspend fun initiateSepa(
        amountCents: Int,
        sourceIban: String,
        sourceName: String,
    ): TopUpResult {
        val response = api.initiateSepaTopUp(
            SepaTopUpRequest(
                amountCents = amountCents,
                sourceIban = sourceIban,
                sourceName = sourceName,
            )
        )
        return TopUpResult(
            topUpId = response.topUpId,
            authorisationUrl = response.authorisationUrl,
            reference = response.reference,
        )
    }

    /** Get top-up history */
    suspend fun getHistory(limit: Int = 20): List<TopUpHistoryItem> {
        val response = api.getTopUpHistory(limit)
        return response.topups
    }

    /** Get all EU/EEA PSD2 banks (optionally by country) */
    suspend fun getBanks(countryCode: String? = null): BankListResponse {
        return api.getTopUpBanks(countryCode)
    }
}

// ── Data classes ────────────────────────────────────

data class TopUpResult(
    val topUpId: String,
    val authorisationUrl: String,
    val reference: String,
)

data class IdealTopUpRequest(
    @SerializedName("amount_cents") val amountCents: Int,
    @SerializedName("source_bic") val sourceBic: String? = null,
)

data class SepaTopUpRequest(
    @SerializedName("amount_cents") val amountCents: Int,
    @SerializedName("source_iban") val sourceIban: String,
    @SerializedName("source_name") val sourceName: String,
)

data class TopUpHistoryItem(
    val id: String,
    @SerializedName("amount_cents") val amountCents: Int,
    val method: String,
    val status: String,
    @SerializedName("source_bic") val sourceBic: String?,
    val reference: String,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("completed_at") val completedAt: String?,
)

data class TopUpHistoryResponse(
    val topups: List<TopUpHistoryItem>,
)

data class EuBank(
    val bic: String,
    val name: String,
    val country: String,
    val countryName: String,
)

data class BankListResponse(
    val banks: List<EuBank>,
    val countries: List<String>,
    val total: Int,
)
