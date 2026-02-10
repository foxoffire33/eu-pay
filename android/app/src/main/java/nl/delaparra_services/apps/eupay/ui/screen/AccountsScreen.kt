package nl.delaparra_services.apps.eupay.ui.screen

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import nl.delaparra_services.apps.eupay.model.LinkedAccountResponse
import nl.delaparra_services.apps.eupay.model.MandateResponse
import nl.delaparra_services.apps.eupay.ui.viewmodel.AccountsViewModel
import nl.delaparra_services.apps.eupay.ui.viewmodel.LinkStep

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AccountsScreen(
    onNavigateBack: () -> Unit,
    viewModel: AccountsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Bank Accounts") },
                navigationIcon = {
                    IconButton(onClick = {
                        when {
                            state.selectedAccount != null -> viewModel.clearSelectedAccount()
                            state.linkStep != LinkStep.IDLE -> viewModel.cancelLinkFlow()
                            else -> onNavigateBack()
                        }
                    }) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back")
                    }
                },
            )
        },
        floatingActionButton = {
            if (state.linkStep == LinkStep.IDLE && state.selectedAccount == null) {
                ExtendedFloatingActionButton(
                    onClick = { viewModel.startLinkFlow() },
                    icon = { Icon(Icons.Default.Add, "Link") },
                    text = { Text("Link Account") },
                )
            }
        },
    ) { padding ->
        when {
            state.isLoading && state.accounts.isEmpty() -> {
                Box(
                    modifier = Modifier.fillMaxSize().padding(padding),
                    contentAlignment = Alignment.Center,
                ) { CircularProgressIndicator() }
            }
            state.selectedAccount != null -> {
                AccountDetailView(
                    state = state,
                    viewModel = viewModel,
                    modifier = Modifier.padding(padding),
                )
            }
            state.linkStep != LinkStep.IDLE -> {
                LinkFlowView(
                    state = state,
                    viewModel = viewModel,
                    modifier = Modifier.padding(padding),
                )
            }
            else -> {
                AccountListView(
                    state = state,
                    viewModel = viewModel,
                    modifier = Modifier.padding(padding),
                )
            }
        }
    }
}

// ── Account List ──

@Composable
private fun AccountListView(
    state: nl.delaparra_services.apps.eupay.ui.viewmodel.AccountsUiState,
    viewModel: AccountsViewModel,
    modifier: Modifier = Modifier,
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        // Onboarding status banner
        val onboarding = state.onboarding
        if (onboarding != null && !onboarding.ready) {
            item {
                OnboardingBanner(onboarding)
            }
        }

        // Mandate status
        val mandate = state.mandateStatus
        if (mandate != null) {
            item {
                MandateCard(mandate, onRevoke = { viewModel.revokeMandate() })
            }
        }

        // Error
        if (state.error != null) {
            item {
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.errorContainer,
                    ),
                ) {
                    Text(
                        text = state.error!!,
                        modifier = Modifier.padding(16.dp),
                        color = MaterialTheme.colorScheme.onErrorContainer,
                    )
                }
            }
        }

        // Accounts header
        item {
            Text(
                text = "Linked Accounts",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
            )
        }

        if (state.accounts.isEmpty()) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Column(
                        modifier = Modifier.padding(24.dp).fillMaxWidth(),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Icon(
                            Icons.Default.AccountBalance,
                            contentDescription = null,
                            modifier = Modifier.size(48.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        Text(
                            text = "No bank accounts linked yet",
                            style = MaterialTheme.typography.bodyLarge,
                        )
                        Text(
                            text = "Link your bank account via PSD2 to get started",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        } else {
            items(state.accounts) { account ->
                AccountCard(account, onClick = { viewModel.selectAccount(account) })
            }

            // Show mandate creation if no active mandate and accounts exist
            if (mandate == null || !mandate.isActive) {
                item {
                    Spacer(modifier = Modifier.height(4.dp))
                    val activeAccount = state.accounts.firstOrNull { it.isActive }
                    if (activeAccount != null) {
                        OutlinedButton(
                            onClick = { viewModel.createMandate(activeAccount.id) },
                            modifier = Modifier.fillMaxWidth(),
                            enabled = !state.isLoading,
                        ) {
                            Icon(Icons.Default.Receipt, contentDescription = null)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Enable Euro-incasso")
                        }
                        if (mandate?.status == "pending") {
                            Spacer(modifier = Modifier.height(8.dp))
                            Button(
                                onClick = { viewModel.activateMandate() },
                                modifier = Modifier.fillMaxWidth(),
                                enabled = !state.isLoading,
                            ) {
                                Text("Sign & Activate Mandate")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun OnboardingBanner(onboarding: nl.delaparra_services.apps.eupay.model.OnboardingStatusResponse) {
    Card(
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.secondaryContainer,
        ),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = "Complete Setup",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSecondaryContainer,
            )
            Spacer(modifier = Modifier.height(8.dp))
            OnboardingStep("Link bank account", onboarding.bankLinked)
            OnboardingStep("Create debit card", onboarding.cardIssued)
            OnboardingStep("Enable Euro-incasso", onboarding.mandateActive)
        }
    }
}

@Composable
private fun OnboardingStep(label: String, completed: Boolean) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.padding(vertical = 2.dp),
    ) {
        Icon(
            if (completed) Icons.Default.CheckCircle else Icons.Default.RadioButtonUnchecked,
            contentDescription = null,
            modifier = Modifier.size(20.dp),
            tint = if (completed) MaterialTheme.colorScheme.primary
            else MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(modifier = Modifier.width(8.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = if (completed) MaterialTheme.colorScheme.onSecondaryContainer
            else MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun AccountCard(account: LinkedAccountResponse, onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Default.AccountBalance,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                )
                Spacer(modifier = Modifier.width(12.dp))
                Column {
                    Text(
                        text = account.displayName,
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = FontWeight.Medium,
                    )
                    Text(
                        text = account.maskedIban,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            StatusBadge(account.status, account.needsRefresh)
        }
    }
}

@Composable
private fun StatusBadge(status: String, needsRefresh: Boolean) {
    val (color, text) = when {
        needsRefresh -> MaterialTheme.colorScheme.error to "Refresh"
        status == "active" -> MaterialTheme.colorScheme.primary to "Active"
        status == "pending_consent" -> MaterialTheme.colorScheme.tertiary to "Pending"
        else -> MaterialTheme.colorScheme.onSurfaceVariant to status
    }
    Surface(
        color = color.copy(alpha = 0.12f),
        shape = MaterialTheme.shapes.small,
    ) {
        Text(
            text = text,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
            style = MaterialTheme.typography.labelSmall,
            color = color,
        )
    }
}

@Composable
private fun MandateCard(mandate: MandateResponse, onRevoke: () -> Unit) {
    Card(
        colors = CardDefaults.cardColors(
            containerColor = if (mandate.isActive) MaterialTheme.colorScheme.primaryContainer
            else MaterialTheme.colorScheme.surfaceVariant,
        ),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Euro-incasso",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                )
                Text(
                    text = if (mandate.isActive) "Active" else mandate.status.replaceFirstChar { it.uppercase() },
                    style = MaterialTheme.typography.bodySmall,
                    color = if (mandate.isActive) MaterialTheme.colorScheme.primary
                    else MaterialTheme.colorScheme.onSurfaceVariant,
                )
                if (mandate.bankName != null) {
                    Text(
                        text = "${mandate.bankName} ••${mandate.ibanLastFour ?: ""}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            if (mandate.isActive) {
                TextButton(
                    onClick = onRevoke,
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = MaterialTheme.colorScheme.error,
                    ),
                ) { Text("Revoke") }
            }
        }
    }
}

// ── Link Flow ──

@Composable
private fun LinkFlowView(
    state: nl.delaparra_services.apps.eupay.ui.viewmodel.AccountsUiState,
    viewModel: AccountsViewModel,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 24.dp)
            .verticalScroll(rememberScrollState()),
    ) {
        Spacer(modifier = Modifier.height(8.dp))

        // Step indicator
        LinearProgressIndicator(
            progress = {
                when (state.linkStep) {
                    LinkStep.SELECT_BANK -> 0.25f
                    LinkStep.ENTER_IBAN -> 0.5f
                    LinkStep.LINKING -> 0.75f
                    LinkStep.SCA_REDIRECT -> 0.9f
                    LinkStep.DONE -> 1f
                    else -> 0f
                }
            },
            modifier = Modifier.fillMaxWidth(),
        )

        Spacer(modifier = Modifier.height(24.dp))

        when (state.linkStep) {
            LinkStep.SELECT_BANK -> {
                Text(
                    text = "Select your country",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                )
                Spacer(modifier = Modifier.height(16.dp))

                if (state.countries.isNotEmpty()) {
                    state.countries.forEach { country ->
                        OutlinedCard(
                            onClick = {
                                viewModel.selectCountry(country)
                                viewModel.proceedToIban()
                            },
                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                        ) {
                            Row(
                                modifier = Modifier.padding(16.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Text(
                                    text = countryFlag(country),
                                    style = MaterialTheme.typography.titleLarge,
                                )
                                Spacer(modifier = Modifier.width(12.dp))
                                Text(text = countryName(country))
                            }
                        }
                    }
                } else {
                    CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
                }

                Spacer(modifier = Modifier.height(16.dp))
                TextButton(onClick = { viewModel.proceedToIban() }) {
                    Text("Skip — enter IBAN directly")
                }
            }

            LinkStep.ENTER_IBAN -> {
                Text(
                    text = "Enter your IBAN",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                )
                Spacer(modifier = Modifier.height(16.dp))

                OutlinedTextField(
                    value = state.iban,
                    onValueChange = { viewModel.updateIban(it) },
                    label = { Text("IBAN") },
                    placeholder = { Text("NL00 BANK 0000 0000 00") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                Spacer(modifier = Modifier.height(12.dp))

                OutlinedTextField(
                    value = state.label,
                    onValueChange = { viewModel.updateLabel(it) },
                    label = { Text("Label (optional)") },
                    placeholder = { Text("e.g. Savings account") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                if (state.error != null) {
                    Spacer(modifier = Modifier.height(12.dp))
                    Card(
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.errorContainer,
                        ),
                    ) {
                        Text(
                            text = state.error!!,
                            modifier = Modifier.padding(16.dp),
                            color = MaterialTheme.colorScheme.onErrorContainer,
                        )
                    }
                }

                Spacer(modifier = Modifier.height(24.dp))

                Button(
                    onClick = { viewModel.submitLinkAccount() },
                    modifier = Modifier.fillMaxWidth().height(50.dp),
                ) { Text("Link Account") }
            }

            LinkStep.LINKING -> {
                Box(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 48.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        CircularProgressIndicator()
                        Spacer(modifier = Modifier.height(16.dp))
                        Text("Connecting to your bank...")
                    }
                }
            }

            LinkStep.SCA_REDIRECT -> {
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Column(
                        modifier = Modifier.padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Icon(
                            Icons.Default.OpenInBrowser,
                            contentDescription = null,
                            modifier = Modifier.size(48.dp),
                            tint = MaterialTheme.colorScheme.onPrimaryContainer,
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Text(
                            text = "Authorise at your bank",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.SemiBold,
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = "You will be redirected to your bank to authorise access to your account (PSD2 SCA).",
                            style = MaterialTheme.typography.bodyMedium,
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(
                            onClick = {
                                state.authorisationUrl?.let {
                                    context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(it)))
                                }
                            },
                        ) { Text("Open Bank") }
                        Spacer(modifier = Modifier.height(8.dp))
                        TextButton(
                            onClick = { viewModel.finishLinkFlow(); viewModel.refresh() },
                        ) { Text("I've completed authorisation") }
                    }
                }
            }

            LinkStep.DONE -> {
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Column(
                        modifier = Modifier.padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Icon(
                            Icons.Default.CheckCircle,
                            contentDescription = null,
                            modifier = Modifier.size(48.dp),
                            tint = MaterialTheme.colorScheme.primary,
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Text(
                            text = "Account linked!",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.SemiBold,
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = { viewModel.finishLinkFlow() }) {
                            Text("Done")
                        }
                    }
                }
            }

            else -> {}
        }
    }
}

// ── Account Detail ──

@Composable
private fun AccountDetailView(
    state: nl.delaparra_services.apps.eupay.ui.viewmodel.AccountsUiState,
    viewModel: AccountsViewModel,
    modifier: Modifier = Modifier,
) {
    val account = state.selectedAccount ?: return
    var showUnlinkConfirm by remember { mutableStateOf(false) }

    if (showUnlinkConfirm) {
        AlertDialog(
            onDismissRequest = { showUnlinkConfirm = false },
            title = { Text("Unlink Account") },
            text = { Text("Are you sure you want to unlink ${account.displayName}? This will revoke PSD2 consent.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showUnlinkConfirm = false
                        viewModel.unlinkAccount(account.id)
                    },
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = MaterialTheme.colorScheme.error,
                    ),
                ) { Text("Unlink") }
            },
            dismissButton = {
                TextButton(onClick = { showUnlinkConfirm = false }) { Text("Cancel") }
            },
        )
    }

    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        // Account info header
        item {
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer,
                ),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = account.displayName,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = account.maskedIban,
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.8f),
                    )
                    if (account.consentValidUntil != null) {
                        Text(
                            text = "Consent valid until: ${account.consentValidUntil.take(10)}",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.6f),
                        )
                    }
                }
            }
        }

        // Balance
        item {
            Text(
                text = "Balance",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
            )
        }

        item {
            if (state.balance != null) {
                val balanceEntry = state.balance.balances?.firstOrNull()
                val amount = (balanceEntry?.get("balanceAmount") as? Map<*, *>)
                    ?.get("amount") as? String
                val currency = (balanceEntry?.get("balanceAmount") as? Map<*, *>)
                    ?.get("currency") as? String

                Card(modifier = Modifier.fillMaxWidth()) {
                    Text(
                        text = if (amount != null) {
                            String.format("%s %s", if (currency == "EUR") "\u20AC" else currency ?: "EUR", amount)
                        } else {
                            "Balance unavailable"
                        },
                        modifier = Modifier.padding(16.dp),
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                    )
                }
            } else {
                CircularProgressIndicator(modifier = Modifier.size(24.dp))
            }
        }

        // Transactions
        item {
            Text(
                text = "Recent Transactions",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
            )
        }

        val transactions = state.transactions?.transactions
        if (transactions.isNullOrEmpty()) {
            item {
                Text(
                    text = if (state.transactions == null) "Loading..." else "No transactions found",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        } else {
            items(transactions.take(20)) { tx ->
                val name = tx["creditorName"] as? String
                    ?: tx["debtorName"] as? String
                    ?: "Unknown"
                val amount = (tx["transactionAmount"] as? Map<*, *>)?.get("amount") as? String ?: "0.00"
                val currency = (tx["transactionAmount"] as? Map<*, *>)?.get("currency") as? String ?: "EUR"
                val date = tx["bookingDate"] as? String ?: ""

                Card(modifier = Modifier.fillMaxWidth()) {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = name,
                                style = MaterialTheme.typography.bodyMedium,
                                fontWeight = FontWeight.Medium,
                            )
                            Text(
                                text = date,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        Text(
                            text = "${if (currency == "EUR") "\u20AC" else currency} $amount",
                            style = MaterialTheme.typography.bodyLarge,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                }
            }
        }

        // Actions
        item {
            Spacer(modifier = Modifier.height(8.dp))
            if (account.needsRefresh) {
                Button(
                    onClick = { viewModel.refreshConsent(account.id) },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Refresh Consent") }
                Spacer(modifier = Modifier.height(8.dp))
            }
            OutlinedButton(
                onClick = { showUnlinkConfirm = true },
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = MaterialTheme.colorScheme.error,
                ),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Icon(Icons.Default.LinkOff, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Unlink Account")
            }
        }
    }
}

// ── Helpers ──

private fun countryFlag(code: String): String {
    if (code.length != 2) return code
    val first = Character.toChars(0x1F1E6 + (code[0].uppercaseChar() - 'A'))
    val second = Character.toChars(0x1F1E6 + (code[1].uppercaseChar() - 'A'))
    return String(first) + String(second)
}

private fun countryName(code: String): String = when (code.uppercase()) {
    "NL" -> "Netherlands"
    "DE" -> "Germany"
    "BE" -> "Belgium"
    "FR" -> "France"
    "AT" -> "Austria"
    "IE" -> "Ireland"
    "ES" -> "Spain"
    "IT" -> "Italy"
    "PT" -> "Portugal"
    "FI" -> "Finland"
    "LU" -> "Luxembourg"
    "EE" -> "Estonia"
    "LT" -> "Lithuania"
    "LV" -> "Latvia"
    "SK" -> "Slovakia"
    "SI" -> "Slovenia"
    "GR" -> "Greece"
    "MT" -> "Malta"
    "CY" -> "Cyprus"
    else -> code
}
