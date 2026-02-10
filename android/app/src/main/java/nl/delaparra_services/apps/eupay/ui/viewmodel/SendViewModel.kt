package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.service.P2PService
import javax.inject.Inject

enum class SendTab { USER, IBAN }

data class SendUiState(
    val tab: SendTab = SendTab.USER,
    // User send
    val recipientEmail: String = "",
    val amountEuro: String = "",
    val message: String = "",
    // IBAN send
    val recipientIban: String = "",
    val recipientName: String = "",
    val ibanAmount: String = "",
    val ibanMessage: String = "",
    // Status
    val isLoading: Boolean = false,
    val error: String? = null,
    val successMessage: String? = null,
)

@HiltViewModel
class SendViewModel @Inject constructor(
    private val p2pService: P2PService,
) : ViewModel() {

    private val _state = MutableStateFlow(SendUiState())
    val state: StateFlow<SendUiState> = _state.asStateFlow()

    fun setTab(tab: SendTab) {
        _state.value = _state.value.copy(tab = tab, error = null, successMessage = null)
    }

    fun updateUserFields(email: String? = null, amount: String? = null, message: String? = null) {
        val s = _state.value
        _state.value = s.copy(
            recipientEmail = email ?: s.recipientEmail,
            amountEuro = amount ?: s.amountEuro,
            message = message ?: s.message,
        )
    }

    fun updateIbanFields(
        iban: String? = null,
        name: String? = null,
        amount: String? = null,
        message: String? = null,
    ) {
        val s = _state.value
        _state.value = s.copy(
            recipientIban = iban ?: s.recipientIban,
            recipientName = name ?: s.recipientName,
            ibanAmount = amount ?: s.ibanAmount,
            ibanMessage = message ?: s.ibanMessage,
        )
    }

    fun sendToUser() {
        val s = _state.value
        val cents = parseEuroToCents(s.amountEuro)
        if (s.recipientEmail.isBlank() || cents == null || cents <= 0) {
            _state.value = s.copy(error = "Enter a valid email and amount")
            return
        }
        _state.value = s.copy(isLoading = true, error = null, successMessage = null)
        viewModelScope.launch {
            try {
                val result = p2pService.sendToUser(s.recipientEmail, cents, s.message.ifBlank { null })
                _state.value = _state.value.copy(
                    isLoading = false,
                    successMessage = "Sent! Reference: ${result.transferId}",
                    recipientEmail = "",
                    amountEuro = "",
                    message = "",
                )
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Transfer failed",
                )
            }
        }
    }

    fun sendToIban() {
        val s = _state.value
        val cents = parseEuroToCents(s.ibanAmount)
        if (s.recipientIban.isBlank() || s.recipientName.isBlank() || cents == null || cents <= 0) {
            _state.value = s.copy(error = "All fields are required")
            return
        }
        if (!p2pService.isValidIban(s.recipientIban)) {
            _state.value = s.copy(error = "Invalid IBAN")
            return
        }
        _state.value = s.copy(isLoading = true, error = null, successMessage = null)
        viewModelScope.launch {
            try {
                val result = p2pService.sendToIban(
                    recipientIban = s.recipientIban,
                    recipientName = s.recipientName,
                    amountCents = cents,
                    message = s.ibanMessage.ifBlank { null },
                )
                _state.value = _state.value.copy(
                    isLoading = false,
                    successMessage = "Sent! Reference: ${result.transferId}",
                    recipientIban = "",
                    recipientName = "",
                    ibanAmount = "",
                    ibanMessage = "",
                )
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Transfer failed",
                )
            }
        }
    }

    private fun parseEuroToCents(euro: String): Int? {
        return try {
            (euro.toDouble() * 100).toInt()
        } catch (e: NumberFormatException) {
            null
        }
    }
}
