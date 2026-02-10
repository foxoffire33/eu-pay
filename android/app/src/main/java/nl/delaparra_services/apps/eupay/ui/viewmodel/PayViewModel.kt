package nl.delaparra_services.apps.eupay.ui.viewmodel

import android.app.Application
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.model.CardResponse
import nl.delaparra_services.apps.eupay.service.CardService
import nl.delaparra_services.apps.eupay.service.PaymentService
import nl.delaparra_services.apps.eupay.util.DeviceFingerprint
import javax.inject.Inject

data class PayUiState(
    val cards: List<CardResponse> = emptyList(),
    val selectedCardId: String? = null,
    val isReadyToPay: Boolean = false,
    val isLoading: Boolean = false,
    val error: String? = null,
    val statusMessage: String? = null,
)

@HiltViewModel
class PayViewModel @Inject constructor(
    private val paymentService: PaymentService,
    private val cardService: CardService,
    private val application: Application,
) : ViewModel() {

    private val _state = MutableStateFlow(PayUiState())
    val state: StateFlow<PayUiState> = _state.asStateFlow()

    init {
        loadCards()
    }

    private fun loadCards() {
        viewModelScope.launch {
            cardService.getCards()
                .onSuccess { cards ->
                    val active = cards.filter { it.isActive }
                    _state.value = _state.value.copy(
                        cards = active,
                        selectedCardId = active.firstOrNull()?.id,
                    )
                }
        }
    }

    fun selectCard(cardId: String) {
        _state.value = _state.value.copy(selectedCardId = cardId, isReadyToPay = false)
    }

    fun activatePayment() {
        val cardId = _state.value.selectedCardId ?: return
        _state.value = _state.value.copy(isLoading = true, error = null, statusMessage = null)
        viewModelScope.launch {
            val fingerprint = DeviceFingerprint.generatePrivacyPreserving(application)
            paymentService.provisionCard(cardId, fingerprint)
                .onSuccess { provision ->
                    paymentService.activateForPayment(provision.tokenId)
                        .onSuccess {
                            _state.value = _state.value.copy(
                                isLoading = false,
                                isReadyToPay = true,
                                statusMessage = "Ready — tap your phone to pay",
                            )
                        }
                        .onFailure { e ->
                            _state.value = _state.value.copy(
                                isLoading = false,
                                error = e.message ?: "Activation failed",
                            )
                        }
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = e.message ?: "Provisioning failed",
                    )
                }
        }
    }

    fun deactivatePayment() {
        paymentService.clearActivePayment()
        _state.value = _state.value.copy(
            isReadyToPay = false,
            statusMessage = null,
        )
    }
}
