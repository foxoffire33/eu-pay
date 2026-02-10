package com.example.eupay.api

import com.example.eupay.model.*
import com.example.eupay.service.*
import retrofit2.Response
import retrofit2.http.*

interface EuPayApi {

    // ── Auth ────────────────────────────────────

    @POST("api/register")
    suspend fun register(@Body request: RegisterRequest): Response<RegisterResponse>

    @POST("api/login_check")
    suspend fun login(@Body request: LoginRequest): Response<AuthResponse>

    @GET("api/me")
    suspend fun getProfile(): Response<UserProfile>

    @POST("api/me/rotate-key")
    suspend fun rotateKey(@Body request: KeyRotateRequest): Response<Map<String, String>>

    // ── Account ─────────────────────────────────

    @POST("api/account/create")
    suspend fun createAccount(): Response<AccountResponse>

    @GET("api/account/balance")
    suspend fun getBalance(): Response<BalanceResponse>

    @GET("api/account/transactions")
    suspend fun getTransactions(): Response<List<TransactionResponse>>

    // ── Cards ───────────────────────────────────

    @GET("api/cards")
    suspend fun getCards(): Response<List<CardResponse>>

    @POST("api/cards/virtual")
    suspend fun createVirtualCard(): Response<CardResponse>

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
    suspend fun updateConsent(@Body request: ConsentUpdateRequest): Response<ConsentResponse>

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
