package nl.delaparra_services.apps.eupay.ui.viewmodel

import android.app.Activity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.gson.Gson
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.service.AuthService
import nl.delaparra_services.apps.eupay.service.PasskeyService
import javax.inject.Inject

data class LoginUiState(
    val isLoading: Boolean = false,
    val error: String? = null,
)

data class RegisterUiState(
    val displayName: String = "",
    val gdprConsent: Boolean = false,
    val isLoading: Boolean = false,
    val error: String? = null,
)

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val authService: AuthService,
    private val passkeyService: PasskeyService,
    private val gson: Gson,
) : ViewModel() {

    private val _loginState = MutableStateFlow(LoginUiState())
    val loginState: StateFlow<LoginUiState> = _loginState.asStateFlow()

    private val _registerState = MutableStateFlow(RegisterUiState())
    val registerState: StateFlow<RegisterUiState> = _registerState.asStateFlow()

    fun updateRegisterField(
        displayName: String? = null,
        gdprConsent: Boolean? = null,
    ) {
        val s = _registerState.value
        _registerState.value = s.copy(
            displayName = displayName ?: s.displayName,
            gdprConsent = gdprConsent ?: s.gdprConsent,
        )
    }

    /**
     * 3-step passkey registration:
     * 1. Get creation options from server (creates user)
     * 2. Create passkey via Credential Manager (system UI)
     * 3. Send attestation to server, receive JWT
     */
    fun register(activity: Activity, onSuccess: () -> Unit) {
        val state = _registerState.value
        if (state.displayName.isBlank()) {
            _registerState.value = state.copy(error = "Display name is required")
            return
        }
        if (!state.gdprConsent) {
            _registerState.value = state.copy(error = "GDPR consent is required")
            return
        }

        _registerState.value = state.copy(isLoading = true, error = null)
        viewModelScope.launch {
            try {
                // Step 1: Get registration options
                val optionsResult = authService.getRegisterOptions(
                    displayName = state.displayName,
                    gdprConsent = state.gdprConsent,
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

                _registerState.value = _registerState.value.copy(isLoading = false)
                onSuccess()
            } catch (e: Exception) {
                _registerState.value = _registerState.value.copy(
                    isLoading = false,
                    error = e.message ?: "Registration failed",
                )
            }
        }
    }

    /**
     * 3-step passkey login:
     * 1. Get authentication options from server
     * 2. Get passkey assertion via Credential Manager (system UI)
     * 3. Send assertion to server, receive JWT
     */
    fun login(activity: Activity, onSuccess: () -> Unit) {
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

    fun clearLoginError() {
        _loginState.value = _loginState.value.copy(error = null)
    }

    fun clearRegisterError() {
        _registerState.value = _registerState.value.copy(error = null)
    }

    fun isLoggedIn(): Boolean = authService.isLoggedIn()

    fun logout() {
        authService.logout()
    }
}
