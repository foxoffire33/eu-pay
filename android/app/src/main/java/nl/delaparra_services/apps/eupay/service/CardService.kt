package nl.delaparra_services.apps.eupay.service

import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.CardResponse

class CardService(private val api: EuPayApi) {

    suspend fun getCards(): Result<List<CardResponse>> {
        return try {
            val response = api.getCards()
            if (response.isSuccessful) {
                Result.success(response.body() ?: emptyList())
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun createDebitCard(): Result<CardResponse> {
        return try {
            val response = api.createDebitCard()
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun blockCard(cardId: String): Result<String> {
        return try {
            val response = api.blockCard(cardId)
            if (response.isSuccessful) {
                Result.success(response.body()?.get("status") ?: "BLOCKED")
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun unblockCard(cardId: String): Result<String> {
        return try {
            val response = api.unblockCard(cardId)
            if (response.isSuccessful) {
                Result.success(response.body()?.get("status") ?: "ACTIVE")
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
