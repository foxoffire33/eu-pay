package nl.delaparra_services.apps.eupay.ui.viewmodel

import android.app.Activity
import androidx.credentials.exceptions.NoCredentialException
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.gson.Gson
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.crypto.ClientKeyManager
import nl.delaparra_services.apps.eupay.repository.TokenRepository
import nl.delaparra_services.apps.eupay.service.AuthService
import nl.delaparra_services.apps.eupay.service.PasskeyService
import java.security.MessageDigest
import javax.inject.Inject

data class LoginUiState(
    val isLoading: Boolean = false,
    val error: String? = null,
    val showGdprConsent: Boolean = false,
    val gdprConsent: Boolean = false,
)

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val authService: AuthService,
    private val passkeyService: PasskeyService,
    private val tokenRepository: TokenRepository,
    private val gson: Gson,
) : ViewModel() {

    private val _loginState = MutableStateFlow(LoginUiState())
    val loginState: StateFlow<LoginUiState> = _loginState.asStateFlow()

    fun updateGdprConsent(consent: Boolean) {
        _loginState.value = _loginState.value.copy(gdprConsent = consent)
    }

    fun dismissGdprConsent() {
        _loginState.value = _loginState.value.copy(showGdprConsent = false, isLoading = false)
    }

    /**
     * Login flow with automatic registration:
     * - New user (no passkey stored) → show GDPR consent → auto-register
     * - Returning user → authenticate via Credential Manager
     */
    fun login(activity: Activity, onSuccess: () -> Unit) {
        // New user: skip Credential Manager, go straight to registration
        if (!tokenRepository.hasPasskey()) {
            _loginState.value = _loginState.value.copy(showGdprConsent = true)
            return
        }

        _loginState.value = _loginState.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            try {
                // Step 1: Get login options
                val optionsResult = authService.getLoginOptions()
                val optionsResponse = optionsResult.getOrThrow()

                // Step 2: Get passkey assertion via system UI
                val optionsJson = gson.toJson(optionsResponse.options)
                val credentialJson = passkeyService.getPasskey(optionsJson, activity)

                // Step 3: Verify assertion on server
                authService.completeLogin(
                    challengeToken = optionsResponse.challengeToken,
                    credentialJson = credentialJson,
                ).getOrThrow()

                _loginState.value = _loginState.value.copy(isLoading = false)
                onSuccess()
            } catch (e: Exception) {
                _loginState.value = _loginState.value.copy(
                    isLoading = false,
                    error = e.message ?: "Sign in failed",
                )
            }
        }
    }

    /**
     * Auto-register after GDPR consent:
     * 1. Get/create RSA-4096 key pair
     * 2. displayName = SHA-512(publicKey)
     * 3. Register passkey on server
     */
    fun confirmRegistration(activity: Activity, onSuccess: () -> Unit) {
        if (!_loginState.value.gdprConsent) {
            _loginState.value = _loginState.value.copy(error = "GDPR consent is required")
            return
        }

        _loginState.value = _loginState.value.copy(isLoading = true, showGdprConsent = false, error = null)
        viewModelScope.launch {
            try {
                // Get public key and compute SHA-512 as display name
                val publicKeyBase64 = ClientKeyManager.exportPublicKey()
                val displayName = sha512(publicKeyBase64)

                // Step 1: Get registration options
                val optionsResult = authService.getRegisterOptions(
                    displayName = displayName,
                    gdprConsent = true,
                    publicKey = publicKeyBase64,
                )
                val optionsResponse = optionsResult.getOrThrow()

                // Step 2: Create passkey via system UI
                val optionsJson = gson.toJson(optionsResponse.options)
                val credentialJson = passkeyService.createPasskey(optionsJson, activity)

                // Step 3: Verify attestation on server
                authService.completeRegistration(
                    challengeToken = optionsResponse.challengeToken,
                    credentialJson = credentialJson,
                ).getOrThrow()

                tokenRepository.setHasPasskey()
                _loginState.value = _loginState.value.copy(isLoading = false)
                onSuccess()
            } catch (e: Exception) {
                _loginState.value = _loginState.value.copy(
                    isLoading = false,
                    error = e.message ?: "Registration failed",
                )
            }
        }
    }

    fun clearLoginError() {
        _loginState.value = _loginState.value.copy(error = null)
    }

    fun isLoggedIn(): Boolean = authService.isLoggedIn()

    fun logout() {
        authService.logout()
    }

    private fun sha512(input: String): String {
        val digest = MessageDigest.getInstance("SHA-512")
        val hash = digest.digest(input.toByteArray(Charsets.UTF_8))
        return hash.joinToString("") { "%02x".format(it) }
    }
}
