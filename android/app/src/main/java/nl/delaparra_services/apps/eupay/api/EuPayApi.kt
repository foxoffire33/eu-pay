package nl.delaparra_services.apps.eupay.api

import nl.delaparra_services.apps.eupay.model.*
import nl.delaparra_services.apps.eupay.service.*
import retrofit2.Response
import retrofit2.http.*

interface EuPayApi {

    // ── Auth (Passkey / WebAuthn) ────────────────

    @POST("api/passkey/register/options")
    suspend fun getRegisterOptions(@Body request: PasskeyRegisterOptionsRequest): Response<PasskeyOptionsResponse>

    @POST("api/passkey/register")
    suspend fun completeRegistration(@Body request: PasskeyRegisterRequest): Response<PasskeyRegisterResponse>

    @POST("api/passkey/login/options")
    suspend fun getLoginOptions(@Body body: Map<String, String> = emptyMap()): Response<PasskeyOptionsResponse>

    @POST("api/passkey/login")
    suspend fun completeLogin(@Body request: PasskeyLoginRequest): Response<PasskeyLoginResponse>

    @GET("api/me")
    suspend fun getProfile(): Response<UserProfile>

    @POST("api/me/rotate-key")
    suspend fun rotateKey(@Body request: KeyRotateRequest): Response<Map<String, String>>

    // ── Linked Bank Accounts (PSD2 AISP) ────────

    @POST("api/account/link")
    suspend fun linkBankAccount(@Body request: LinkAccountRequest): Response<LinkAccountResult>

    @POST("api/account/link/callback")
    suspend fun confirmLinkConsent(@Body request: ConsentCallbackRequest): Response<LinkedAccountResponse>

    @GET("api/account/linked")
    suspend fun getLinkedAccounts(): Response<LinkedAccountsListResponse>

    @GET("api/account/{id}/balance")
    suspend fun getLinkedAccountBalance(@Path("id") accountId: String): Response<AccountBalanceResponse>

    @GET("api/account/{id}/transactions")
    suspend fun getLinkedAccountTransactions(
        @Path("id") accountId: String,
        @Query("from") dateFrom: String? = null,
    ): Response<AccountTransactionsResponse>

    @DELETE("api/account/{id}")
    suspend fun unlinkBankAccount(@Path("id") accountId: String): Response<Map<String, String>>

    @POST("api/account/{id}/refresh")
    suspend fun refreshAccountConsent(@Path("id") accountId: String): Response<LinkAccountResult>

    @GET("api/account/banks")
    suspend fun getAccountBanks(@Query("country") countryCode: String? = null): Response<BankListResponse>

    // ── SEPA Direct Debit Mandate ────────────────

    @POST("api/account/mandate")
    suspend fun createMandate(@Body request: CreateMandateRequest): Response<MandateResponse>

    @POST("api/account/mandate/activate")
    suspend fun activateMandate(): Response<MandateResponse>

    @DELETE("api/account/mandate")
    suspend fun revokeMandate(): Response<Map<String, String>>

    @GET("api/account/mandate")
    suspend fun getMandate(): Response<MandateWrapper>

    // ── Onboarding ───────────────────────────────

    @GET("api/account/onboarding-status")
    suspend fun getOnboardingStatus(): Response<OnboardingStatusResponse>

    // ── Cards ───────────────────────────────────

    @GET("api/cards")
    suspend fun getCards(): Response<List<CardResponse>>

    @POST("api/cards/virtual")
    suspend fun createDebitCard(): Response<CardResponse>

    @POST("api/cards/{id}/activate")
    suspend fun activateCard(
        @Path("id") cardId: String,
        @Body body: Map<String, String>
    ): Response<Map<String, String>>

    @POST("api/cards/{id}/block")
    suspend fun blockCard(@Path("id") cardId: String): Response<Map<String, String>>

    @POST("api/cards/{id}/unblock")
    suspend fun unblockCard(@Path("id") cardId: String): Response<Map<String, String>>

    // ── HCE ─────────────────────────────────────

    @POST("api/hce/provision")
    suspend fun provisionHce(@Body request: HceProvisionRequest): Response<HceProvisionResponse>

    @GET("api/hce/payload/{tokenId}")
    suspend fun getHcePayload(@Path("tokenId") tokenId: String): Response<HcePaymentPayload>

    @POST("api/hce/refresh/{tokenId}")
    suspend fun refreshHceToken(@Path("tokenId") tokenId: String): Response<HceRefreshResponse>

    @POST("api/hce/deactivate/{tokenId}")
    suspend fun deactivateHceToken(@Path("tokenId") tokenId: String): Response<Map<String, String>>

    @GET("api/hce/tokens")
    suspend fun getHceTokens(): Response<List<HceTokenInfo>>

    // ── GDPR (EU Data Subject Rights) ──────────

    @GET("api/gdpr/export")
    suspend fun exportData(): Response<Map<String, Any>>

    @POST("api/gdpr/erase")
    suspend fun eraseData(@Body request: EraseRequest): Response<EraseResponse>

    @GET("api/gdpr/consent")
    suspend fun getConsent(): Response<ConsentResponse>

    @PATCH("api/gdpr/consent")
    suspend fun updateConsent(@Body request: ConsentUpdateRequest): Response<ConsentUpdateResponse>

    // ── Top-Up (PSD2 PISP — any EU/EEA bank) ──

    @POST("api/topup/ideal")
    suspend fun initiateIdealTopUp(@Body request: IdealTopUpRequest): TopUpResult

    @POST("api/topup/sepa")
    suspend fun initiateSepaTopUp(@Body request: SepaTopUpRequest): TopUpResult

    @GET("api/topup/history")
    suspend fun getTopUpHistory(@Query("limit") limit: Int = 20): TopUpHistoryResponse

    @GET("api/topup/banks")
    suspend fun getTopUpBanks(@Query("country") countryCode: String? = null): BankListResponse

    // ── P2P Transfers ──────────────────────────

    @POST("api/p2p/send/user")
    suspend fun p2pSendToUser(@Body request: P2PSendUserRequest): P2PResult

    @POST("api/p2p/send/iban")
    suspend fun p2pSendToIban(@Body request: P2PSendIbanRequest): P2PResult

    @GET("api/p2p/history")
    suspend fun getP2PHistory(@Query("limit") limit: Int = 20): P2PHistoryResponse

    @GET("api/p2p/banks")
    suspend fun getP2PBanks(@Query("country") countryCode: String? = null): BankListResponse
}
