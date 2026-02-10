package nl.delaparra_services.apps.eupay.ui.navigation

sealed class Screen(val route: String) {
    // Auth
    data object Login : Screen("login")
    data object Register : Screen("register")

    // Main tabs
    data object Home : Screen("home")
    data object Cards : Screen("cards")
    data object Pay : Screen("pay")
    data object Send : Screen("send")
    data object Settings : Screen("settings")

    // Nested
    data object TopUp : Screen("topup")
}
