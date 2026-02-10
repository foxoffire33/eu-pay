package nl.delaparra_services.apps.eupay.ui.screen

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import nl.delaparra_services.apps.eupay.ui.viewmodel.TopUpMethod
import nl.delaparra_services.apps.eupay.ui.viewmodel.TopUpViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TopUpScreen(
    onNavigateBack: () -> Unit,
    viewModel: TopUpViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Top Up") },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back")
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

            // Method selector
            PrimaryTabRow(selectedTabIndex = if (state.method == TopUpMethod.IDEAL) 0 else 1) {
                Tab(
                    selected = state.method == TopUpMethod.IDEAL,
                    onClick = { viewModel.setMethod(TopUpMethod.IDEAL) },
                    text = { Text("iDEAL") },
                )
                Tab(
                    selected = state.method == TopUpMethod.SEPA,
                    onClick = { viewModel.setMethod(TopUpMethod.SEPA) },
                    text = { Text("SEPA") },
                )
            }

            Spacer(modifier = Modifier.height(24.dp))

            OutlinedTextField(
                value = state.amountEuro,
                onValueChange = { viewModel.updateAmount(it) },
                label = { Text("Amount (\u20AC)") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Decimal,
                    imeAction = if (state.method == TopUpMethod.SEPA) ImeAction.Next else ImeAction.Done,
                ),
                modifier = Modifier.fillMaxWidth(),
            )

            if (state.method == TopUpMethod.SEPA) {
                Spacer(modifier = Modifier.height(12.dp))

                OutlinedTextField(
                    value = state.sourceIban,
                    onValueChange = { viewModel.updateSepaFields(iban = it) },
                    label = { Text("Your IBAN") },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Next),
                    modifier = Modifier.fillMaxWidth(),
                )

                Spacer(modifier = Modifier.height(12.dp))

                OutlinedTextField(
                    value = state.sourceName,
                    onValueChange = { viewModel.updateSepaFields(name = it) },
                    label = { Text("Account Holder Name") },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                    modifier = Modifier.fillMaxWidth(),
                )
            }

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

            if (state.authorisationUrl != null) {
                Spacer(modifier = Modifier.height(12.dp))
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Authorisation required",
                            style = MaterialTheme.typography.titleSmall,
                            color = MaterialTheme.colorScheme.onPrimaryContainer,
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Button(
                            onClick = {
                                context.startActivity(
                                    Intent(Intent.ACTION_VIEW, Uri.parse(state.authorisationUrl))
                                )
                            },
                        ) {
                            Text("Open Bank")
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            Button(
                onClick = { viewModel.initiateTopUp() },
                enabled = !state.isLoading,
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
                    Text(
                        if (state.method == TopUpMethod.IDEAL) "Top Up with iDEAL"
                        else "Top Up with SEPA"
                    )
                }
            }

            Spacer(modifier = Modifier.height(24.dp))
        }
    }
}
