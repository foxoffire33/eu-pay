package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.service.TopUpService
import javax.inject.Inject

enum class TopUpMethod { IDEAL, SEPA }

data class TopUpUiState(
    val method: TopUpMethod = TopUpMethod.IDEAL,
    val amountEuro: String = "",
    // SEPA fields
    val sourceIban: String = "",
    val sourceName: String = "",
    // Status
    val isLoading: Boolean = false,
    val error: String? = null,
    val authorisationUrl: String? = null,
)

@HiltViewModel
class TopUpViewModel @Inject constructor(
    private val topUpService: TopUpService,
) : ViewModel() {

    private val _state = MutableStateFlow(TopUpUiState())
    val state: StateFlow<TopUpUiState> = _state.asStateFlow()

    fun setMethod(method: TopUpMethod) {
        _state.value = _state.value.copy(method = method, error = null, authorisationUrl = null)
    }

    fun updateAmount(amount: String) {
        _state.value = _state.value.copy(amountEuro = amount)
    }

    fun updateSepaFields(iban: String? = null, name: String? = null) {
        val s = _state.value
        _state.value = s.copy(
            sourceIban = iban ?: s.sourceIban,
            sourceName = name ?: s.sourceName,
        )
    }

    fun initiateTopUp() {
        val s = _state.value
        val cents = try {
            (s.amountEuro.toDouble() * 100).toInt()
        } catch (e: NumberFormatException) {
            _state.value = s.copy(error = "Enter a valid amount")
            return
        }
        if (cents <= 0) {
            _state.value = s.copy(error = "Amount must be positive")
            return
        }
        _state.value = s.copy(isLoading = true, error = null, authorisationUrl = null)
        viewModelScope.launch {
            try {
                val result = when (s.method) {
                    TopUpMethod.IDEAL -> topUpService.initiateIdeal(cents)
                    TopUpMethod.SEPA -> {
                        if (s.sourceIban.isBlank() || s.sourceName.isBlank()) {
                            _state.value = s.copy(isLoading = false, error = "IBAN and name required")
                            return@launch
                        }
                        topUpService.initiateSepa(cents, s.sourceIban, s.sourceName)
                    }
                }
                _state.value = _state.value.copy(
                    isLoading = false,
                    authorisationUrl = result.authorisationUrl,
                )
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Top-up failed",
                )
            }
        }
    }
}
