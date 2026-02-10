package nl.delaparra_services.apps.eupay.ui.screen

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import nl.delaparra_services.apps.eupay.ui.viewmodel.SetupWizardViewModel
import nl.delaparra_services.apps.eupay.ui.viewmodel.SetupStep

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SetupWizardScreen(
    onComplete: () -> Unit,
    onSkip: () -> Unit,
    viewModel: SetupWizardViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current

    // Auto-complete if all steps are done
    LaunchedEffect(state.onboarding) {
        if (state.onboarding?.ready == true) {
            onComplete()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Get Started") },
                actions = {
                    TextButton(onClick = onSkip) {
                        Text("Skip")
                    }
                },
            )
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(horizontal = 24.dp)
                .verticalScroll(rememberScrollState()),
        ) {
            Spacer(modifier = Modifier.height(8.dp))

            // Progress indicator
            val progress = listOf(
                state.onboarding?.bankLinked == true,
                state.onboarding?.cardIssued == true,
                state.onboarding?.mandateActive == true,
            ).count { it } / 3f

            LinearProgressIndicator(
                progress = { progress },
                modifier = Modifier.fillMaxWidth(),
            )

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = "${(progress * 3).toInt()}/3 steps completed",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            Spacer(modifier = Modifier.height(24.dp))

            when (state.currentStep) {
                SetupStep.LINK_BANK -> LinkBankStep(state, viewModel, context)
                SetupStep.CREATE_CARD -> CreateCardStep(state, viewModel)
                SetupStep.ENABLE_MANDATE -> EnableMandateStep(state, viewModel)
                SetupStep.COMPLETE -> {
                    CompleteStep(onComplete)
                }
            }

            // Error display
            if (state.error != null) {
                Spacer(modifier = Modifier.height(16.dp))
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

            // Navigation buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                if (state.currentStep != SetupStep.LINK_BANK) {
                    OutlinedButton(onClick = { viewModel.previousStep() }) {
                        Text("Back")
                    }
                } else {
                    Spacer(modifier = Modifier.width(1.dp))
                }

                if (state.currentStep != SetupStep.COMPLETE) {
                    TextButton(onClick = { viewModel.skipCurrentStep() }) {
                        Text("Skip this step")
                    }
                }
            }

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@Composable
private fun LinkBankStep(
    state: nl.delaparra_services.apps.eupay.ui.viewmodel.SetupUiState,
    viewModel: SetupWizardViewModel,
    context: android.content.Context,
) {
    StepHeader(
        icon = Icons.Default.AccountBalance,
        title = "Link your bank account",
        description = "Connect your bank via PSD2 to check your balance and enable payments.",
        completed = state.onboarding?.bankLinked == true,
    )

    if (state.onboarding?.bankLinked == true) {
        Spacer(modifier = Modifier.height(16.dp))
        Card(
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer,
            ),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Default.CheckCircle, null, tint = MaterialTheme.colorScheme.primary)
                Spacer(modifier = Modifier.width(12.dp))
                Text("Bank account linked!")
            }
        }
        Spacer(modifier = Modifier.height(16.dp))
        Button(
            onClick = { viewModel.nextStep() },
            modifier = Modifier.fillMaxWidth(),
        ) { Text("Continue") }
    } else {
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
            placeholder = { Text("e.g. Main account") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )

        if (state.authorisationUrl != null) {
            Spacer(modifier = Modifier.height(16.dp))
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer,
                ),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(
                        text = "Authorise at your bank",
                        style = MaterialTheme.typography.titleSmall,
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Button(onClick = {
                        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(state.authorisationUrl)))
                    }) { Text("Open Bank") }
                    Spacer(modifier = Modifier.height(8.dp))
                    TextButton(onClick = { viewModel.confirmBankLink() }) {
                        Text("I've completed authorisation")
                    }
                }
            }
        } else {
            Spacer(modifier = Modifier.height(24.dp))
            Button(
                onClick = { viewModel.linkAccount() },
                enabled = !state.isLoading && state.iban.isNotBlank(),
                modifier = Modifier.fillMaxWidth().height(50.dp),
            ) {
                if (state.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(20.dp),
                        color = MaterialTheme.colorScheme.onPrimary,
                        strokeWidth = 2.dp,
                    )
                } else {
                    Text("Link Bank Account")
                }
            }
        }
    }
}

@Composable
private fun CreateCardStep(
    state: nl.delaparra_services.apps.eupay.ui.viewmodel.SetupUiState,
    viewModel: SetupWizardViewModel,
) {
    StepHeader(
        icon = Icons.Default.CreditCard,
        title = "Create your debit card",
        description = "Get a virtual Visa debit card for contactless payments (NFC).",
        completed = state.onboarding?.cardIssued == true,
    )

    Spacer(modifier = Modifier.height(16.dp))

    if (state.onboarding?.cardIssued == true) {
        Card(
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer,
            ),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Default.CheckCircle, null, tint = MaterialTheme.colorScheme.primary)
                Spacer(modifier = Modifier.width(12.dp))
                Text("Virtual card created!")
            }
        }
        Spacer(modifier = Modifier.height(16.dp))
        Button(
            onClick = { viewModel.nextStep() },
            modifier = Modifier.fillMaxWidth(),
        ) { Text("Continue") }
    } else {
        Button(
            onClick = { viewModel.createCard() },
            enabled = !state.isLoading,
            modifier = Modifier.fillMaxWidth().height(50.dp),
        ) {
            if (state.isLoading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(20.dp),
                    color = MaterialTheme.colorScheme.onPrimary,
                    strokeWidth = 2.dp,
                )
            } else {
                Icon(Icons.Default.CreditCard, null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Create Virtual Card")
            }
        }
    }
}

@Composable
private fun EnableMandateStep(
    state: nl.delaparra_services.apps.eupay.ui.viewmodel.SetupUiState,
    viewModel: SetupWizardViewModel,
) {
    StepHeader(
        icon = Icons.Default.Receipt,
        title = "Enable Euro-incasso",
        description = "Authorise SEPA Direct Debit to automatically fund your card from your bank account.",
        completed = state.onboarding?.mandateActive == true,
    )

    Spacer(modifier = Modifier.height(16.dp))

    if (state.onboarding?.mandateActive == true) {
        Card(
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer,
            ),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Default.CheckCircle, null, tint = MaterialTheme.colorScheme.primary)
                Spacer(modifier = Modifier.width(12.dp))
                Text("Euro-incasso enabled!")
            }
        }
        Spacer(modifier = Modifier.height(16.dp))
        Button(
            onClick = { viewModel.nextStep() },
            modifier = Modifier.fillMaxWidth(),
        ) { Text("Finish Setup") }
    } else {
        if (state.mandatePending) {
            Button(
                onClick = { viewModel.activateMandate() },
                enabled = !state.isLoading,
                modifier = Modifier.fillMaxWidth().height(50.dp),
            ) {
                if (state.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(20.dp),
                        color = MaterialTheme.colorScheme.onPrimary,
                        strokeWidth = 2.dp,
                    )
                } else {
                    Text("Sign & Activate Mandate")
                }
            }
        } else {
            Button(
                onClick = { viewModel.createAndActivateMandate() },
                enabled = !state.isLoading,
                modifier = Modifier.fillMaxWidth().height(50.dp),
            ) {
                if (state.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(20.dp),
                        color = MaterialTheme.colorScheme.onPrimary,
                        strokeWidth = 2.dp,
                    )
                } else {
                    Icon(Icons.Default.Receipt, null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Enable Euro-incasso")
                }
            }
        }
    }
}

@Composable
private fun CompleteStep(onComplete: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(modifier = Modifier.height(32.dp))

        Icon(
            Icons.Default.CheckCircle,
            contentDescription = null,
            modifier = Modifier.size(80.dp),
            tint = MaterialTheme.colorScheme.primary,
        )

        Spacer(modifier = Modifier.height(24.dp))

        Text(
            text = "You're all set!",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center,
        )

        Spacer(modifier = Modifier.height(8.dp))

        Text(
            text = "Your EU Pay account is ready. You can now make contactless payments, send money, and top up your balance.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
        )

        Spacer(modifier = Modifier.height(32.dp))

        Button(
            onClick = onComplete,
            modifier = Modifier.fillMaxWidth().height(50.dp),
        ) { Text("Start Using EU Pay") }
    }
}

@Composable
private fun StepHeader(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    description: String,
    completed: Boolean,
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Surface(
            shape = MaterialTheme.shapes.medium,
            color = if (completed) MaterialTheme.colorScheme.primary.copy(alpha = 0.12f)
            else MaterialTheme.colorScheme.surfaceVariant,
            modifier = Modifier.size(56.dp),
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(
                    icon,
                    contentDescription = null,
                    modifier = Modifier.size(28.dp),
                    tint = if (completed) MaterialTheme.colorScheme.primary
                    else MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        Spacer(modifier = Modifier.width(16.dp))
        Column {
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
            )
            Text(
                text = description,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}
