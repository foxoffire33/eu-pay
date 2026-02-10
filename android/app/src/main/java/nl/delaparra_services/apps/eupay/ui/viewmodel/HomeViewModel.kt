package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.model.LinkedAccountResponse
import nl.delaparra_services.apps.eupay.model.OnboardingStatusResponse
import nl.delaparra_services.apps.eupay.service.AccountService
import javax.inject.Inject

data class HomeUiState(
    val linkedAccounts: List<LinkedAccountResponse> = emptyList(),
    val balanceAmount: String? = null,
    val balanceCurrency: String = "EUR",
    val onboarding: OnboardingStatusResponse? = null,
    val isLoading: Boolean = false,
    val error: String? = null,
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val accountService: AccountService,
) : ViewModel() {

    private val _state = MutableStateFlow(HomeUiState())
    val state: StateFlow<HomeUiState> = _state.asStateFlow()

    init {
        refresh()
    }

    fun refresh() {
        _state.value = _state.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            try {
                // Load onboarding status
                accountService.getOnboardingStatus()
                    .onSuccess { onboarding ->
                        _state.value = _state.value.copy(onboarding = onboarding)
                    }

                // Load linked accounts
                accountService.getLinkedAccounts()
                    .onSuccess { accounts ->
                        _state.value = _state.value.copy(linkedAccounts = accounts)

                        // Load balance from first active account
                        val activeAccount = accounts.firstOrNull { it.isActive }
                        if (activeAccount != null) {
                            accountService.getBalance(activeAccount.id)
                                .onSuccess { balance ->
                                    val entry = balance.balances?.firstOrNull()
                                    val amount = (entry?.get("balanceAmount") as? Map<*, *>)
                                        ?.get("amount") as? String
                                    val currency = (entry?.get("balanceAmount") as? Map<*, *>)
                                        ?.get("currency") as? String
                                    _state.value = _state.value.copy(
                                        balanceAmount = amount,
                                        balanceCurrency = currency ?: "EUR",
                                    )
                                }
                        }
                    }

                _state.value = _state.value.copy(isLoading = false)
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Failed to load data",
                )
            }
        }
    }
}
