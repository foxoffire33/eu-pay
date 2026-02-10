package nl.delaparra_services.apps.eupay.ui.screen

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Contactless
import androidx.compose.material.icons.filled.CreditCard
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import nl.delaparra_services.apps.eupay.ui.viewmodel.PayViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PayScreen(
    viewModel: PayViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(title = { Text("Tap to Pay") })
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            if (state.cards.isEmpty()) {
                Spacer(modifier = Modifier.weight(1f))
                Icon(
                    Icons.Default.CreditCard,
                    contentDescription = null,
                    modifier = Modifier.size(64.dp),
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "No active cards",
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Text(
                    text = "Create and activate a card first",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(modifier = Modifier.weight(1f))
            } else {
                // Card selector
                Text(
                    text = "Select Card",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(modifier = Modifier.height(8.dp))

                state.cards.forEach { card ->
                    val selected = card.id == state.selectedCardId
                    OutlinedCard(
                        onClick = { viewModel.selectCard(card.id) },
                        modifier = Modifier.fillMaxWidth(),
                        border = if (selected) CardDefaults.outlinedCardBorder().copy(
                            width = 2.dp,
                        ) else CardDefaults.outlinedCardBorder(),
                        colors = CardDefaults.outlinedCardColors(
                            containerColor = if (selected)
                                MaterialTheme.colorScheme.primaryContainer
                            else
                                MaterialTheme.colorScheme.surface,
                        ),
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            RadioButton(
                                selected = selected,
                                onClick = { viewModel.selectCard(card.id) },
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(card.displayName)
                        }
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                }

                Spacer(modifier = Modifier.weight(1f))

                // NFC animation area
                Icon(
                    Icons.Default.Contactless,
                    contentDescription = null,
                    modifier = Modifier.size(120.dp),
                    tint = if (state.isReadyToPay)
                        MaterialTheme.colorScheme.primary
                    else
                        MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.3f),
                )

                Spacer(modifier = Modifier.height(16.dp))

                if (state.statusMessage != null) {
                    Text(
                        text = state.statusMessage!!,
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.primary,
                        fontWeight = FontWeight.SemiBold,
                        textAlign = TextAlign.Center,
                    )
                }

                if (state.error != null) {
                    Text(
                        text = state.error!!,
                        color = MaterialTheme.colorScheme.error,
                        style = MaterialTheme.typography.bodyMedium,
                        textAlign = TextAlign.Center,
                    )
                }

                Spacer(modifier = Modifier.height(24.dp))

                if (state.isReadyToPay) {
                    OutlinedButton(
                        onClick = { viewModel.deactivatePayment() },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(50.dp),
                    ) {
                        Text("Cancel Payment")
                    }
                } else {
                    Button(
                        onClick = { viewModel.activatePayment() },
                        enabled = !state.isLoading && state.selectedCardId != null,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(50.dp),
                    ) {
                        if (state.isLoading) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                color = MaterialTheme.colorScheme.onPrimary,
                                strokeWidth = 2.dp,
                            )
                        } else {
                            Text("Activate Tap to Pay")
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))
            }
        }
    }
}
