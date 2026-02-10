package com.example.eupay.service

import com.example.eupay.api.EuPayApi
import com.example.eupay.model.CardResponse

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

    suspend fun createVirtualCard(): Result<CardResponse> {
        return try {
            val response = api.createVirtualCard()
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
