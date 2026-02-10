package nl.delaparra_services.apps.eupay.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import nl.delaparra_services.apps.eupay.model.*
import nl.delaparra_services.apps.eupay.service.AccountService
import nl.delaparra_services.apps.eupay.service.EuBank
import javax.inject.Inject

enum class LinkStep { IDLE, SELECT_BANK, ENTER_IBAN, LINKING, SCA_REDIRECT, DONE }

data class AccountsUiState(
    val accounts: List<LinkedAccountResponse> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    // Link flow
    val linkStep: LinkStep = LinkStep.IDLE,
    val banks: List<EuBank> = emptyList(),
    val selectedCountry: String? = null,
    val countries: List<String> = emptyList(),
    val iban: String = "",
    val label: String = "",
    val authorisationUrl: String? = null,
    // Detail
    val selectedAccount: LinkedAccountResponse? = null,
    val balance: AccountBalanceResponse? = null,
    val transactions: AccountTransactionsResponse? = null,
    // Mandate
    val mandateStatus: MandateResponse? = null,
    // Onboarding
    val onboarding: OnboardingStatusResponse? = null,
)

@HiltViewModel
class AccountsViewModel @Inject constructor(
    private val accountService: AccountService,
) : ViewModel() {

    private val _state = MutableStateFlow(AccountsUiState())
    val state: StateFlow<AccountsUiState> = _state.asStateFlow()

    init {
        refresh()
    }

    fun refresh() {
        _state.value = _state.value.copy(isLoading = true, error = null)
        viewModelScope.launch {
            // Load accounts, mandate status, and onboarding in parallel
            val accountsResult = accountService.getLinkedAccounts()
            val mandateResult = accountService.getMandateStatus()
            val onboardingResult = accountService.getOnboardingStatus()

            accountsResult
                .onSuccess { accounts ->
                    _state.value = _state.value.copy(accounts = accounts)
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message)
                }

            mandateResult
                .onSuccess { mandate ->
                    _state.value = _state.value.copy(mandateStatus = mandate)
                }

            onboardingResult
                .onSuccess { onboarding ->
                    _state.value = _state.value.copy(onboarding = onboarding)
                }

            _state.value = _state.value.copy(isLoading = false)
        }
    }

    // ── Link Flow ──

    fun startLinkFlow() {
        _state.value = _state.value.copy(
            linkStep = LinkStep.SELECT_BANK,
            error = null,
            iban = "",
            label = "",
            authorisationUrl = null,
        )
        loadBanks()
    }

    fun cancelLinkFlow() {
        _state.value = _state.value.copy(linkStep = LinkStep.IDLE)
    }

    private fun loadBanks(country: String? = null) {
        viewModelScope.launch {
            accountService.getBanks(country)
                .onSuccess { response ->
                    _state.value = _state.value.copy(
                        banks = response.banks,
                        countries = response.countries,
                    )
                }
        }
    }

    fun selectCountry(country: String) {
        _state.value = _state.value.copy(selectedCountry = country)
        loadBanks(country)
    }

    fun proceedToIban() {
        _state.value = _state.value.copy(linkStep = LinkStep.ENTER_IBAN)
    }

    fun updateIban(iban: String) {
        _state.value = _state.value.copy(iban = iban.uppercase())
    }

    fun updateLabel(label: String) {
        _state.value = _state.value.copy(label = label)
    }

    fun submitLinkAccount() {
        val s = _state.value
        if (s.iban.isBlank()) {
            _state.value = s.copy(error = "IBAN is required")
            return
        }
        _state.value = s.copy(linkStep = LinkStep.LINKING, error = null)
        viewModelScope.launch {
            accountService.linkAccount(
                iban = s.iban.replace(" ", ""),
                label = s.label.ifBlank { null },
            ).onSuccess { result ->
                _state.value = _state.value.copy(
                    linkStep = LinkStep.SCA_REDIRECT,
                    authorisationUrl = result.authorisationUrl,
                )
            }.onFailure { e ->
                _state.value = _state.value.copy(
                    linkStep = LinkStep.ENTER_IBAN,
                    error = e.message ?: "Failed to link account",
                )
            }
        }
    }

    fun confirmConsent(consentId: String, success: Boolean) {
        viewModelScope.launch {
            accountService.confirmConsent(consentId, success)
                .onSuccess {
                    _state.value = _state.value.copy(linkStep = LinkStep.DONE)
                    refresh()
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message)
                }
        }
    }

    fun finishLinkFlow() {
        _state.value = _state.value.copy(linkStep = LinkStep.IDLE)
    }

    // ── Account Detail ──

    fun selectAccount(account: LinkedAccountResponse) {
        _state.value = _state.value.copy(
            selectedAccount = account,
            balance = null,
            transactions = null,
        )
        loadAccountDetail(account.id)
    }

    fun clearSelectedAccount() {
        _state.value = _state.value.copy(
            selectedAccount = null,
            balance = null,
            transactions = null,
        )
    }

    private fun loadAccountDetail(accountId: String) {
        viewModelScope.launch {
            accountService.getBalance(accountId)
                .onSuccess { balance ->
                    _state.value = _state.value.copy(balance = balance)
                }
            accountService.getTransactions(accountId)
                .onSuccess { transactions ->
                    _state.value = _state.value.copy(transactions = transactions)
                }
        }
    }

    fun unlinkAccount(accountId: String) {
        viewModelScope.launch {
            accountService.unlinkAccount(accountId)
                .onSuccess {
                    _state.value = _state.value.copy(selectedAccount = null)
                    refresh()
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message)
                }
        }
    }

    fun refreshConsent(accountId: String) {
        viewModelScope.launch {
            accountService.refreshConsent(accountId)
                .onSuccess { result ->
                    _state.value = _state.value.copy(
                        authorisationUrl = result.authorisationUrl,
                    )
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message)
                }
        }
    }

    // ── Mandate ──

    fun createMandate(accountId: String) {
        viewModelScope.launch {
            _state.value = _state.value.copy(isLoading = true, error = null)
            accountService.createMandate(accountId)
                .onSuccess { mandate ->
                    _state.value = _state.value.copy(mandateStatus = mandate, isLoading = false)
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message, isLoading = false)
                }
        }
    }

    fun activateMandate() {
        viewModelScope.launch {
            _state.value = _state.value.copy(isLoading = true, error = null)
            accountService.activateMandate()
                .onSuccess { mandate ->
                    _state.value = _state.value.copy(mandateStatus = mandate, isLoading = false)
                    refresh()
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message, isLoading = false)
                }
        }
    }

    fun revokeMandate() {
        viewModelScope.launch {
            accountService.revokeMandate()
                .onSuccess {
                    _state.value = _state.value.copy(mandateStatus = null)
                    refresh()
                }
                .onFailure { e ->
                    _state.value = _state.value.copy(error = e.message)
                }
        }
    }

    fun clearError() {
        _state.value = _state.value.copy(error = null)
    }
}
