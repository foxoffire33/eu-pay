package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.model.OnboardingStatusResponse
import nl.delaparra_services.apps.eupay.service.AccountService
import nl.delaparra_services.apps.eupay.service.CardService
import javax.inject.Inject

enum class SetupStep { LINK_BANK, CREATE_CARD, ENABLE_MANDATE, COMPLETE }

data class SetupUiState(
    val currentStep: SetupStep = SetupStep.LINK_BANK,
    val onboarding: OnboardingStatusResponse? = null,
    val isLoading: Boolean = false,
    val error: String? = null,
    // Bank linking
    val iban: String = "",
    val label: String = "",
    val authorisationUrl: String? = null,
    // Mandate
    val mandatePending: Boolean = false,
)

@HiltViewModel
class SetupWizardViewModel @Inject constructor(
    private val accountService: AccountService,
    private val cardService: CardService,
) : ViewModel() {

    private val _state = MutableStateFlow(SetupUiState())
    val state: StateFlow<SetupUiState> = _state.asStateFlow()

    init {
        refreshOnboarding()
    }

    private fun refreshOnboarding() {
        viewModelScope.launch {
            accountService.getOnboardingStatus()
                .onSuccess { onboarding ->
                    _state.value = _state.value.copy(onboarding = onboarding)
                    // Auto-advance to first incomplete step
                    if (onboarding.bankLinked && _state.value.currentStep == SetupStep.LINK_BANK) {
                        _state.value = _state.value.copy(currentStep = SetupStep.CREATE_CARD)
                    }
                    if (onboarding.cardIssued && _state.value.currentStep == SetupStep.CREATE_CARD) {
                        _state.value = _state.value.copy(currentStep = SetupStep.ENABLE_MANDATE)
                    }
                    if (onboarding.mandateActive && _state.value.currentStep == SetupStep.ENABLE_MANDATE) {
                        _state.value = _state.value.copy(currentStep = SetupStep.COMPLETE)
                    }
                }
        }
    }

    fun nextStep() {
        val next = when (_state.value.currentStep) {
            SetupStep.LINK_BANK -> SetupStep.CREATE_CARD
            SetupStep.CREATE_CARD -> SetupStep.ENABLE_MANDATE
            SetupStep.ENABLE_MANDATE -> SetupStep.COMPLETE
            SetupStep.COMPLETE -> SetupStep.COMPLETE
        }
        _state.value = _state.value.copy(currentStep = next, error = null, authorisationUrl = null)
    }

    fun previousStep() {
        val prev = when (_state.value.currentStep) {
            SetupStep.LINK_BANK -> SetupStep.LINK_BANK
            SetupStep.CREATE_CARD -> SetupStep.LINK_BANK
            SetupStep.ENABLE_MANDATE -> SetupStep.CREATE_CARD
            SetupStep.COMPLETE -> SetupStep.ENABLE_MANDATE
        }
        _state.value = _state.value.copy(currentStep = prev, error = null)
    }

    fun skipCurrentStep() {
        nextStep()
    }

    fun updateIban(iban: String) {
        _state.value = _state.value.copy(iban = iban.uppercase())
    }

    fun updateLabel(label: String) {
        _state.value = _state.value.copy(label = label)
    }

    // ── Step 1: Link Bank Account ──

    fun linkAccount() {
        val s = _state.value
        if (s.iban.isBlank()) {
            _state.value = s.copy(error = "IBAN is required")
            return
        }
        _state.value = s.copy(isLoading = true, error = null)
        viewModelScope.launch {
            accountService.linkAccount(
                iban = s.iban.replace(" ", ""),
                label = s.label.ifBlank { null },
            ).onSuccess { result ->
                _state.value = _state.value.copy(
                    isLoading = false,
                    authorisationUrl = result.authorisationUrl,
                )
            }.onFailure { e ->
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Failed to link account",
                )
            }
        }
    }

    fun confirmBankLink() {
        _state.value = _state.value.copy(authorisationUrl = null, isLoading = true)
        viewModelScope.launch {
            refreshOnboarding()
            _state.value = _state.value.copy(isLoading = false)
            if (_state.value.onboarding?.bankLinked == true) {
                nextStep()
            }
        }
    }

    // ── Step 2: Create Card ──

    fun createCard() {
        _state.value = _state.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            cardService.createDebitCard()
                .onSuccess {
                    _state.value = _state.value.copy(isLoading = false)
                    refreshOnboarding()
                    nextStep()
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = e.message ?: "Failed to create card",
                    )
                }
        }
    }

    // ── Step 3: Enable Euro-incasso Mandate ──

    fun createAndActivateMandate() {
        _state.value = _state.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            // First, get the active account to create mandate for
            accountService.getLinkedAccounts()
                .onSuccess { accounts ->
                    val activeAccount = accounts.firstOrNull { it.isActive }
                    if (activeAccount == null) {
                        _state.value = _state.value.copy(
                            isLoading = false,
                            error = "Please link a bank account first",
                        )
                        return@onSuccess
                    }

                    accountService.createMandate(activeAccount.id)
                        .onSuccess {
                            _state.value = _state.value.copy(mandatePending = true)
                            // Auto-activate
                            accountService.activateMandate()
                                .onSuccess {
                                    _state.value = _state.value.copy(isLoading = false, mandatePending = false)
                                    refreshOnboarding()
                                    nextStep()
                                }
                                .onFailure { e ->
                                    _state.value = _state.value.copy(
                                        isLoading = false,
                                        error = e.message ?: "Failed to activate mandate",
                                    )
                                }
                        }
                        .onFailure { e ->
                            _state.value = _state.value.copy(
                                isLoading = false,
                                error = e.message ?: "Failed to create mandate",
                            )
                        }
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = e.message ?: "Failed to load accounts",
                    )
                }
        }
    }

    fun activateMandate() {
        _state.value = _state.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            accountService.activateMandate()
                .onSuccess {
                    _state.value = _state.value.copy(isLoading = false, mandatePending = false)
                    refreshOnboarding()
                    nextStep()
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = e.message ?: "Failed to activate mandate",
                    )
                }
        }
    }
}
