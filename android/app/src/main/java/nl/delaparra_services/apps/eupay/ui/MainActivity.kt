package nl.delaparra_services.apps.eupay.ui

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import dagger.hilt.android.AndroidEntryPoint
import nl.delaparra_services.apps.eupay.repository.TokenRepository
import nl.delaparra_services.apps.eupay.ui.navigation.EuPayNavGraph
import nl.delaparra_services.apps.eupay.ui.theme.EuPayTheme
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    @Inject
    lateinit var tokenRepository: TokenRepository

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            EuPayTheme {
                EuPayNavGraph(isLoggedIn = tokenRepository.isLoggedIn())
            }
        }
    }
}
