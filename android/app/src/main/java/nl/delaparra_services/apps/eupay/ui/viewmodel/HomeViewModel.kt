package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.BalanceResponse
import nl.delaparra_services.apps.eupay.model.TransactionResponse
import javax.inject.Inject

data class HomeUiState(
    val balance: BalanceResponse? = null,
    val transactions: List<TransactionResponse> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val api: EuPayApi,
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
                val balanceResp = api.getBalance()
                val txResp = api.getTransactions()
                _state.value = _state.value.copy(
                    balance = balanceResp.body(),
                    transactions = txResp.body()?.transactions ?: emptyList(),
                    isLoading = false,
                )
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Failed to load data",
                )
            }
        }
    }
}
