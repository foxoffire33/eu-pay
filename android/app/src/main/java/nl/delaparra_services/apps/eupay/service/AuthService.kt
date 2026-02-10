package nl.delaparra_services.apps.eupay.service

import com.google.gson.Gson
import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.*
import nl.delaparra_services.apps.eupay.repository.TokenRepository

class AuthService(
    private val api: EuPayApi,
    private val tokenRepo: TokenRepository,
    private val gson: Gson,
) {
    /**
     * Step 1 of registration: request creation options from server.
     */
    suspend fun getRegisterOptions(
        displayName: String,
        gdprConsent: Boolean,
    ): Result<PasskeyOptionsResponse> {
        return try {
            val response = api.getRegisterOptions(
                PasskeyRegisterOptionsRequest(
                    displayName = displayName,
                    gdprConsent = gdprConsent,
                )
            )
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
     * Step 2 of registration: send attestation credential to server.
     */
    suspend fun completeRegistration(
        challengeToken: String,
        credentialJson: String,
    ): Result<PasskeyRegisterResponse> {
        return try {
            val credentialMap: Map<String, Any> = gson.fromJson(
                credentialJson,
                Map::class.java
            ) as Map<String, Any>

            val response = api.completeRegistration(
                PasskeyRegisterRequest(
                    challengeToken = challengeToken,
                    credential = credentialMap,
                )
            )
            if (response.isSuccessful && response.body() != null) {
                val result = response.body()!!
                tokenRepo.saveToken(result.token)
                tokenRepo.saveUserId(result.userId)
                Result.success(result)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Step 1 of login: request authentication options from server.
     */
    suspend fun getLoginOptions(): Result<PasskeyOptionsResponse> {
        return try {
            val response = api.getLoginOptions()
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
     * Step 2 of login: send assertion credential to server.
     */
    suspend fun completeLogin(
        challengeToken: String,
        credentialJson: String,
    ): Result<PasskeyLoginResponse> {
        return try {
            val credentialMap: Map<String, Any> = gson.fromJson(
                credentialJson,
                Map::class.java
            ) as Map<String, Any>

            val response = api.completeLogin(
                PasskeyLoginRequest(
                    challengeToken = challengeToken,
                    credential = credentialMap,
                )
            )
            if (response.isSuccessful && response.body() != null) {
                val result = response.body()!!
                tokenRepo.saveToken(result.token)
                tokenRepo.saveUserId(result.userId)
                Result.success(result)
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
