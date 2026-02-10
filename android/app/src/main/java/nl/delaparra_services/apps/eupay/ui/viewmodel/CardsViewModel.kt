package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.model.CardResponse
import nl.delaparra_services.apps.eupay.service.CardService
import javax.inject.Inject

data class CardsUiState(
    val cards: List<CardResponse> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
)

@HiltViewModel
class CardsViewModel @Inject constructor(
    private val cardService: CardService,
) : ViewModel() {

    private val _state = MutableStateFlow(CardsUiState())
    val state: StateFlow<CardsUiState> = _state.asStateFlow()

    init {
        loadCards()
    }

    fun loadCards() {
        _state.value = _state.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            cardService.getCards()
                .onSuccess { cards ->
                    _state.value = _state.value.copy(cards = cards, isLoading = false)
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = e.message ?: "Failed to load cards",
                    )
                }
        }
    }

    fun createDebitCard() {
        _state.value = _state.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            cardService.createDebitCard()
                .onSuccess { loadCards() }
                .onFailure { e ->
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = e.message ?: "Failed to create card",
                    )
                }
        }
    }

    fun blockCard(cardId: String) {
        viewModelScope.launch {
            cardService.blockCard(cardId)
                .onSuccess { loadCards() }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message)
                }
        }
    }

    fun unblockCard(cardId: String) {
        viewModelScope.launch {
            cardService.unblockCard(cardId)
                .onSuccess { loadCards() }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message)
                }
        }
    }
}
