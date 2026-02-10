package nl.delaparra_services.apps.eupay.service

import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.*
import javax.inject.Inject
import javax.inject.Singleton

/**
 * PSD2 AISP bank account linking + SEPA Direct Debit mandate management.
 */
@Singleton
class AccountService @Inject constructor(
    private val api: EuPayApi,
) {
    // ── Bank Account Linking ──

    suspend fun linkAccount(iban: String, bic: String? = null, label: String? = null): Result<LinkAccountResult> {
        return try {
            val response = api.linkBankAccount(LinkAccountRequest(iban, bic, label))
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun confirmConsent(consentId: String, success: Boolean): Result<LinkedAccountResponse> {
        return try {
            val response = api.confirmLinkConsent(ConsentCallbackRequest(consentId, success))
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getLinkedAccounts(): Result<List<LinkedAccountResponse>> {
        return try {
            val response = api.getLinkedAccounts()
            if (response.isSuccessful) {
                Result.success(response.body()?.accounts ?: emptyList())
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getBalance(accountId: String): Result<AccountBalanceResponse> {
        return try {
            val response = api.getLinkedAccountBalance(accountId)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getTransactions(accountId: String, dateFrom: String? = null): Result<AccountTransactionsResponse> {
        return try {
            val response = api.getLinkedAccountTransactions(accountId, dateFrom)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun unlinkAccount(accountId: String): Result<Unit> {
        return try {
            val response = api.unlinkBankAccount(accountId)
            if (response.isSuccessful) Result.success(Unit)
            else Result.failure(ApiException(response.code(), response.errorBody()?.string()))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshConsent(accountId: String): Result<LinkAccountResult> {
        return try {
            val response = api.refreshAccountConsent(accountId)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getBanks(countryCode: String? = null): Result<BankListResponse> {
        return try {
            val response = api.getAccountBanks(countryCode)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    // ── SEPA Direct Debit Mandate ──

    suspend fun createMandate(accountId: String, maxAmountCents: Int = 50000): Result<MandateResponse> {
        return try {
            val response = api.createMandate(CreateMandateRequest(accountId, maxAmountCents))
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun activateMandate(): Result<MandateResponse> {
        return try {
            val response = api.activateMandate()
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun revokeMandate(): Result<Unit> {
        return try {
            val response = api.revokeMandate()
            if (response.isSuccessful) Result.success(Unit)
            else Result.failure(ApiException(response.code(), response.errorBody()?.string()))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getMandateStatus(): Result<MandateResponse?> {
        return try {
            val response = api.getMandate()
            if (response.isSuccessful) {
                Result.success(response.body()?.mandate)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    // ── Onboarding ──

    suspend fun getOnboardingStatus(): Result<OnboardingStatusResponse> {
        return try {
            val response = api.getOnboardingStatus()
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code(), response.errorBody()?.string()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
