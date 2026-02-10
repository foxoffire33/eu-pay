package nl.delaparra_services.apps.eupay.ui.screen

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Security
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import nl.delaparra_services.apps.eupay.ui.viewmodel.SettingsViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    onLogout: () -> Unit,
    viewModel: SettingsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    if (state.showDeleteConfirm) {
        AlertDialog(
            onDismissRequest = { viewModel.dismissDeleteConfirm() },
            title = { Text("Delete Account") },
            text = {
                Text("This will permanently delete your account and all associated data (GDPR Art. 17). This action cannot be undone.")
            },
            confirmButton = {
                TextButton(
                    onClick = { viewModel.deleteAccount(onLogout) },
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = MaterialTheme.colorScheme.error,
                    ),
                ) {
                    Text("Delete")
                }
            },
            dismissButton = {
                TextButton(onClick = { viewModel.dismissDeleteConfirm() }) {
                    Text("Cancel")
                }
            },
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(title = { Text("Settings") })
        },
    ) { padding ->
        if (state.isLoading && state.profile == null) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
                contentAlignment = Alignment.Center,
            ) {
                CircularProgressIndicator()
            }
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .verticalScroll(rememberScrollState()),
            ) {
                // Profile section
                ListItem(
                    headlineContent = {
                        Text("Profile", fontWeight = FontWeight.SemiBold)
                    },
                    leadingContent = {
                        Icon(Icons.Default.Person, contentDescription = null)
                    },
                )

                val profile = state.profile
                if (profile != null) {
                    ListItem(
                        headlineContent = { Text("User ID") },
                        supportingContent = { Text(profile.id) },
                    )
                    ListItem(
                        headlineContent = { Text("Bank Account") },
                        supportingContent = {
                            Text(if (profile.hasBankAccount == true) "Linked" else "Not linked")
                        },
                    )
                }

                HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))

                // Privacy section
                ListItem(
                    headlineContent = {
                        Text("Privacy & GDPR", fontWeight = FontWeight.SemiBold)
                    },
                    leadingContent = {
                        Icon(Icons.Default.Security, contentDescription = null)
                    },
                )

                val consent = state.consent
                if (consent != null) {
                    ListItem(
                        headlineContent = { Text("Device Tracking") },
                        supportingContent = {
                            Text("ePrivacy Directive Art. 5(3)")
                        },
                        trailingContent = {
                            Switch(
                                checked = consent.deviceTrackingConsent,
                                onCheckedChange = {
                                    viewModel.updateConsent(deviceTracking = it)
                                },
                            )
                        },
                    )

                    ListItem(
                        headlineContent = { Text("Marketing Communications") },
                        trailingContent = {
                            Switch(
                                checked = consent.marketingConsent,
                                onCheckedChange = {
                                    viewModel.updateConsent(marketing = it)
                                },
                            )
                        },
                    )

                    if (consent.privacyPolicyVersion != null) {
                        ListItem(
                            headlineContent = { Text("Privacy Policy") },
                            supportingContent = {
                                Text("Version ${consent.privacyPolicyVersion}")
                            },
                        )
                    }
                }

                HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))

                if (state.error != null) {
                    Card(
                        modifier = Modifier.padding(horizontal = 16.dp),
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
                    Spacer(modifier = Modifier.height(8.dp))
                }

                // Actions
                TextButton(
                    onClick = { viewModel.logout(onLogout) },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp),
                ) {
                    Icon(
                        Icons.AutoMirrored.Filled.Logout,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp),
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Log Out")
                }

                Spacer(modifier = Modifier.height(8.dp))

                TextButton(
                    onClick = { viewModel.showDeleteConfirm() },
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = MaterialTheme.colorScheme.error,
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp),
                ) {
                    Icon(
                        Icons.Default.Delete,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp),
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Delete Account (GDPR Art. 17)")
                }

                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }
}
