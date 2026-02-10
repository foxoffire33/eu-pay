package nl.delaparra_services.apps.eupay.ui.screen

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import nl.delaparra_services.apps.eupay.ui.viewmodel.SendTab
import nl.delaparra_services.apps.eupay.ui.viewmodel.SendViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SendScreen(
    viewModel: SendViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(title = { Text("Send Money") })
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

            // Tab selector
            PrimaryTabRow(selectedTabIndex = if (state.tab == SendTab.USER) 0 else 1) {
                Tab(
                    selected = state.tab == SendTab.USER,
                    onClick = { viewModel.setTab(SendTab.USER) },
                    text = { Text("EU Pay User") },
                )
                Tab(
                    selected = state.tab == SendTab.IBAN,
                    onClick = { viewModel.setTab(SendTab.IBAN) },
                    text = { Text("Bank (IBAN)") },
                )
            }

            Spacer(modifier = Modifier.height(24.dp))

            if (state.tab == SendTab.USER) {
                UserSendForm(
                    email = state.recipientEmail,
                    amount = state.amountEuro,
                    message = state.message,
                    onEmailChange = { viewModel.updateUserFields(email = it) },
                    onAmountChange = { viewModel.updateUserFields(amount = it) },
                    onMessageChange = { viewModel.updateUserFields(message = it) },
                    onSend = { viewModel.sendToUser() },
                    isLoading = state.isLoading,
                )
            } else {
                IbanSendForm(
                    iban = state.recipientIban,
                    name = state.recipientName,
                    amount = state.ibanAmount,
                    message = state.ibanMessage,
                    onIbanChange = { viewModel.updateIbanFields(iban = it) },
                    onNameChange = { viewModel.updateIbanFields(name = it) },
                    onAmountChange = { viewModel.updateIbanFields(amount = it) },
                    onMessageChange = { viewModel.updateIbanFields(message = it) },
                    onSend = { viewModel.sendToIban() },
                    isLoading = state.isLoading,
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

            if (state.successMessage != null) {
                Spacer(modifier = Modifier.height(12.dp))
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                ) {
                    Text(
                        text = state.successMessage!!,
                        modifier = Modifier.padding(16.dp),
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                    )
                }
            }

            Spacer(modifier = Modifier.height(24.dp))
        }
    }
}

@Composable
private fun UserSendForm(
    email: String,
    amount: String,
    message: String,
    onEmailChange: (String) -> Unit,
    onAmountChange: (String) -> Unit,
    onMessageChange: (String) -> Unit,
    onSend: () -> Unit,
    isLoading: Boolean,
) {
    OutlinedTextField(
        value = email,
        onValueChange = onEmailChange,
        label = { Text("Recipient Email") },
        singleLine = true,
        keyboardOptions = KeyboardOptions(
            keyboardType = KeyboardType.Email,
            imeAction = ImeAction.Next,
        ),
        modifier = Modifier.fillMaxWidth(),
    )

    Spacer(modifier = Modifier.height(12.dp))

    OutlinedTextField(
        value = amount,
        onValueChange = onAmountChange,
        label = { Text("Amount (\u20AC)") },
        singleLine = true,
        keyboardOptions = KeyboardOptions(
            keyboardType = KeyboardType.Decimal,
            imeAction = ImeAction.Next,
        ),
        modifier = Modifier.fillMaxWidth(),
    )

    Spacer(modifier = Modifier.height(12.dp))

    OutlinedTextField(
        value = message,
        onValueChange = onMessageChange,
        label = { Text("Message (optional)") },
        singleLine = true,
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
        modifier = Modifier.fillMaxWidth(),
    )

    Spacer(modifier = Modifier.height(24.dp))

    Button(
        onClick = onSend,
        enabled = !isLoading,
        modifier = Modifier
            .fillMaxWidth()
            .height(50.dp),
    ) {
        if (isLoading) {
            CircularProgressIndicator(
                modifier = Modifier.size(20.dp),
                color = MaterialTheme.colorScheme.onPrimary,
                strokeWidth = 2.dp,
            )
        } else {
            Text("Send")
        }
    }
}

@Composable
private fun IbanSendForm(
    iban: String,
    name: String,
    amount: String,
    message: String,
    onIbanChange: (String) -> Unit,
    onNameChange: (String) -> Unit,
    onAmountChange: (String) -> Unit,
    onMessageChange: (String) -> Unit,
    onSend: () -> Unit,
    isLoading: Boolean,
) {
    OutlinedTextField(
        value = iban,
        onValueChange = onIbanChange,
        label = { Text("Recipient IBAN") },
        singleLine = true,
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Next),
        modifier = Modifier.fillMaxWidth(),
    )

    Spacer(modifier = Modifier.height(12.dp))

    OutlinedTextField(
        value = name,
        onValueChange = onNameChange,
        label = { Text("Recipient Name") },
        singleLine = true,
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Next),
        modifier = Modifier.fillMaxWidth(),
    )

    Spacer(modifier = Modifier.height(12.dp))

    OutlinedTextField(
        value = amount,
        onValueChange = onAmountChange,
        label = { Text("Amount (\u20AC)") },
        singleLine = true,
        keyboardOptions = KeyboardOptions(
            keyboardType = KeyboardType.Decimal,
            imeAction = ImeAction.Next,
        ),
        modifier = Modifier.fillMaxWidth(),
    )

    Spacer(modifier = Modifier.height(12.dp))

    OutlinedTextField(
        value = message,
        onValueChange = onMessageChange,
        label = { Text("Message (optional)") },
        singleLine = true,
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
        modifier = Modifier.fillMaxWidth(),
    )

    Spacer(modifier = Modifier.height(24.dp))

    Button(
        onClick = onSend,
        enabled = !isLoading,
        modifier = Modifier
            .fillMaxWidth()
            .height(50.dp),
    ) {
        if (isLoading) {
            CircularProgressIndicator(
                modifier = Modifier.size(20.dp),
                color = MaterialTheme.colorScheme.onPrimary,
                strokeWidth = 2.dp,
            )
        } else {
            Text("Send to IBAN")
        }
    }
}
