package com.example.eupay.service

import com.example.eupay.api.EuPayApi
import com.example.eupay.hce.HcePaymentDataHolder
import com.example.eupay.model.*
import com.example.eupay.util.UuidV6

/**
 * Orchestrates HCE payment token lifecycle:
 * provision → fetch payload → activate → tap → refresh/deactivate
 */
class PaymentService(private val api: EuPayApi) {

    /**
     * Provision a card for HCE NFC payments on this device.
     * Returns the tokenId (UUIDv6) for subsequent operations.
     */
    suspend fun provisionCard(cardId: String, deviceFingerprint: String): Result<HceProvisionResponse> {
        require(cardId.isNotBlank()) { "cardId must not be blank" }
        require(deviceFingerprint.isNotBlank()) { "deviceFingerprint must not be blank" }

        return try {
            val response = api.provisionHce(HceProvisionRequest(cardId, deviceFingerprint))
            if (response.isSuccessful && response.body() != null) {
                val body = response.body()!!
                require(UuidV6.isValid(body.tokenId)) { "Server returned invalid UUIDv6 for token" }
                Result.success(body)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Fetch the payment payload and activate HCE for tap-to-pay.
     * Must be called before the user taps their phone.
     */
    suspend fun activateForPayment(tokenId: String): Result<HcePaymentPayload> {
        return try {
            val response = api.getHcePayload(tokenId)
            if (response.isSuccessful && response.body() != null) {
                val payload = response.body()!!
                HcePaymentDataHolder.setPayload(payload)
                Result.success(payload)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Refresh session keys for an existing HCE token.
     */
    suspend fun refreshToken(tokenId: String): Result<HceRefreshResponse> {
        return try {
            val response = api.refreshHceToken(tokenId)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Deactivate an HCE token and clear local payment data.
     */
    suspend fun deactivateToken(tokenId: String): Result<Unit> {
        return try {
            val response = api.deactivateHceToken(tokenId)
            HcePaymentDataHolder.clear()
            if (response.isSuccessful) {
                Result.success(Unit)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun clearActivePayment() {
        HcePaymentDataHolder.clear()
    }

    fun isReadyForPayment(): Boolean = HcePaymentDataHolder.hasValidPayload()
}

class ApiException(val code: Int, val errorBody: String?) :
    Exception("API error $code: ${errorBody ?: "unknown"}")
