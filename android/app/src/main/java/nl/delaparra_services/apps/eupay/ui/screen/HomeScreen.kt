package nl.delaparra_services.apps.eupay.ui.screen

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalance
import androidx.compose.material.icons.filled.NearMe
import androidx.compose.material.icons.filled.Contactless
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import nl.delaparra_services.apps.eupay.model.TransactionResponse
import nl.delaparra_services.apps.eupay.ui.viewmodel.HomeViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onNavigateToTopUp: () -> Unit,
    onNavigateToPay: () -> Unit,
    onNavigateToSend: () -> Unit,
    viewModel: HomeViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("EU Pay") },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary,
                ),
            )
        },
    ) { padding ->
        if (state.isLoading && state.balance == null) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
                contentAlignment = Alignment.Center,
            ) {
                CircularProgressIndicator()
            }
        } else {
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                // Balance card
                item {
                    val balanceEntry = state.balance?.balances?.firstOrNull()
                    val amount = (balanceEntry?.get("balanceAmount") as? Map<*, *>)
                        ?.get("amount") as? String
                    val currency = (balanceEntry?.get("balanceAmount") as? Map<*, *>)
                        ?.get("currency") as? String
                    BalanceCard(
                        balance = amount?.toDoubleOrNull(),
                        currency = currency ?: "EUR",
                    )
                }

                // Quick actions
                item {
                    QuickActions(
                        onTopUp = onNavigateToTopUp,
                        onPay = onNavigateToPay,
                        onSend = onNavigateToSend,
                    )
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

                // Recent transactions
                item {
                    Text(
                        text = "Recent Transactions",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold,
                    )
                }

                if (state.transactions.isEmpty()) {
                    item {
                        Text(
                            text = "No transactions yet",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                } else {
                    items(state.transactions.take(10)) { tx ->
                        TransactionItem(tx)
                    }
                }
            }
        }
    }
}

@Composable
private fun BalanceCard(balance: Double?, currency: String) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primary,
        ),
    ) {
        Column(
            modifier = Modifier.padding(24.dp),
        ) {
            Text(
                text = "Available Balance",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f),
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = if (balance != null) {
                    String.format("%s %.2f", if (currency == "EUR") "\u20AC" else currency, balance)
                } else {
                    "\u20AC --,--"
                },
                style = MaterialTheme.typography.displaySmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onPrimary,
            )
        }
    }
}

@Composable
private fun QuickActions(
    onTopUp: () -> Unit,
    onPay: () -> Unit,
    onSend: () -> Unit,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        QuickActionButton(
            label = "Top Up",
            icon = Icons.Default.AccountBalance,
            onClick = onTopUp,
            modifier = Modifier.weight(1f),
        )
        QuickActionButton(
            label = "Tap to Pay",
            icon = Icons.Default.Contactless,
            onClick = onPay,
            modifier = Modifier.weight(1f),
        )
        QuickActionButton(
            label = "Send",
            icon = Icons.Default.NearMe,
            onClick = onSend,
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun QuickActionButton(
    label: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    OutlinedCard(
        onClick = onClick,
        modifier = modifier,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(
                icon,
                contentDescription = label,
                tint = MaterialTheme.colorScheme.primary,
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
            )
        }
    }
}

@Composable
private fun TransactionItem(tx: TransactionResponse) {
    Card(
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = tx.type.replaceFirstChar { it.uppercase() },
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Medium,
                )
                Text(
                    text = tx.createdAt.take(10),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    text = "\u20AC${tx.amount}",
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = FontWeight.SemiBold,
                )
                Text(
                    text = tx.status,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
