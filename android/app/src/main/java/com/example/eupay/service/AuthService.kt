package com.example.eupay.service

import com.example.eupay.api.EuPayApi
import com.example.eupay.model.*
import com.example.eupay.repository.TokenRepository

class AuthService(
    private val api: EuPayApi,
    private val tokenRepo: TokenRepository
) {
    suspend fun register(request: RegisterRequest): Result<RegisterResponse> {
        return try {
            val response = api.register(request)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun login(email: String, password: String): Result<AuthResponse> {
        return try {
            val response = api.login(LoginRequest(email, password))
            if (response.isSuccessful && response.body() != null) {
                val auth = response.body()!!
                tokenRepo.saveToken(auth.token)
                Result.success(auth)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getProfile(): Result<UserProfile> {
        return try {
            val response = api.getProfile()
            if (response.isSuccessful && response.body() != null) {
                val profile = response.body()!!
                tokenRepo.saveUserId(profile.id)
                Result.success(profile)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun logout() {
        tokenRepo.clear()
    }

    fun isLoggedIn(): Boolean = tokenRepo.isLoggedIn()
}
