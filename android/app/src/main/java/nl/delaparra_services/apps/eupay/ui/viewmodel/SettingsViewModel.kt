package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.ConsentResponse
import nl.delaparra_services.apps.eupay.model.ConsentUpdateRequest
import nl.delaparra_services.apps.eupay.model.EraseRequest
import nl.delaparra_services.apps.eupay.model.UserProfile
import nl.delaparra_services.apps.eupay.service.AuthService
import javax.inject.Inject

data class SettingsUiState(
    val profile: UserProfile? = null,
    val consent: ConsentResponse? = null,
    val isLoading: Boolean = false,
    val error: String? = null,
    val showDeleteConfirm: Boolean = false,
)

@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val authService: AuthService,
    private val api: EuPayApi,
) : ViewModel() {

    private val _state = MutableStateFlow(SettingsUiState())
    val state: StateFlow<SettingsUiState> = _state.asStateFlow()

    init {
        loadProfile()
    }

    private fun loadProfile() {
        _state.value = _state.value.copy(isLoading = true)
        viewModelScope.launch {
            authService.getProfile()
                .onSuccess { profile ->
                    _state.value = _state.value.copy(profile = profile, isLoading = false)
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = e.message,
                    )
                }
            try {
                val consentResp = api.getConsent()
                if (consentResp.isSuccessful) {
                    _state.value = _state.value.copy(consent = consentResp.body())
                }
            } catch (_: Exception) { }
        }
    }

    fun updateConsent(deviceTracking: Boolean? = null, marketing: Boolean? = null) {
        viewModelScope.launch {
            try {
                val resp = api.updateConsent(
                    ConsentUpdateRequest(
                        deviceTrackingConsent = deviceTracking,
                        marketingConsent = marketing,
                    )
                )
                if (resp.isSuccessful) {
                    // Refresh full consent from GET endpoint
                    val consentResp = api.getConsent()
                    if (consentResp.isSuccessful) {
                        _state.value = _state.value.copy(consent = consentResp.body())
                    }
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(error = e.message)
            }
        }
    }

    fun showDeleteConfirm() {
        _state.value = _state.value.copy(showDeleteConfirm = true)
    }

    fun dismissDeleteConfirm() {
        _state.value = _state.value.copy(showDeleteConfirm = false)
    }

    fun deleteAccount(onDone: () -> Unit) {
        _state.value = _state.value.copy(isLoading = true, showDeleteConfirm = false)
        viewModelScope.launch {
            try {
                api.eraseData(EraseRequest(confirmDeletion = true))
                authService.logout()
                onDone()
            } catch (e: Exception) {
                _state.value = _state.value.copy(isLoading = false, error = e.message)
            }
        }
    }

    fun logout(onDone: () -> Unit) {
        authService.logout()
        onDone()
    }
}
