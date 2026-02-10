package nl.delaparra_services.apps.eupay.ui.theme

import androidx.compose.material3.*
import androidx.compose.runtime.Composable

private val LightColorScheme = lightColorScheme(
    primary = EuBlue,
    onPrimary = White,
    primaryContainer = EuBlueSurface,
    onPrimaryContainer = EuBlueDark,
    secondary = EuGoldDark,
    onSecondary = White,
    secondaryContainer = EuGoldLight,
    onSecondaryContainer = Gray900,
    tertiary = EuGold,
    onTertiary = Gray900,
    background = Gray50,
    onBackground = Gray900,
    surface = White,
    onSurface = Gray900,
    surfaceVariant = Gray100,
    onSurfaceVariant = Gray600,
    outline = Gray400,
    error = Error,
    onError = White,
)

@Composable
fun EuPayTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = LightColorScheme,
        content = content,
    )
}
