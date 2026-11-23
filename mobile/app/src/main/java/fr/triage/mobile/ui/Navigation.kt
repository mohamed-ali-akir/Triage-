package fr.triage.mobile.ui

import android.os.Build
import androidx.compose.runtime.Composable
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import fr.triage.mobile.AppContainer
import fr.triage.mobile.ui.login.LoginScreen
import fr.triage.mobile.ui.login.LoginViewModel
import fr.triage.mobile.ui.ticket.TicketDetailScreen
import fr.triage.mobile.ui.ticket.TicketDetailViewModel
import fr.triage.mobile.ui.tickets.TicketListScreen
import fr.triage.mobile.ui.tickets.TicketListViewModel

private const val ROUTE_LOGIN = "login"
private const val ROUTE_TICKETS = "tickets"
private const val ROUTE_TICKET = "ticket/{id}"

/**
 * The three screens of the application and how to move between them.
 * A stored token opens the ticket list directly; otherwise the login screen.
 */
@Composable
fun TriageNavHost(container: AppContainer) {
    val navController = rememberNavController()
    val start = if (container.authRepository.isLoggedIn()) ROUTE_TICKETS else ROUTE_LOGIN

    NavHost(navController = navController, startDestination = start) {
        composable(ROUTE_LOGIN) {
            val viewModel: LoginViewModel = viewModel(
                factory = viewModelFactory {
                    initializer { LoginViewModel(container.authRepository, deviceName = Build.MODEL ?: "Android") }
                },
            )

            LoginScreen(
                viewModel = viewModel,
                onLoggedIn = {
                    navController.navigate(ROUTE_TICKETS) {
                        popUpTo(ROUTE_LOGIN) { inclusive = true }
                    }
                },
            )
        }

        composable(ROUTE_TICKETS) {
            val viewModel: TicketListViewModel = viewModel(
                factory = viewModelFactory {
                    initializer { TicketListViewModel(container.ticketRepository, container.authRepository) }
                },
            )

            TicketListScreen(
                viewModel = viewModel,
                onTicketClick = { ticketId -> navController.navigate("ticket/$ticketId") },
                onLoggedOut = { navController.backToLogin() },
            )
        }

        composable(
            route = ROUTE_TICKET,
            arguments = listOf(navArgument("id") { type = NavType.IntType }),
        ) { entry ->
            val ticketId = entry.arguments?.getInt("id") ?: return@composable

            val viewModel: TicketDetailViewModel = viewModel(
                factory = viewModelFactory {
                    initializer { TicketDetailViewModel(ticketId, container.ticketRepository) }
                },
            )

            TicketDetailScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() },
                onSessionExpired = { navController.backToLogin() },
            )
        }
    }
}

/** Go to the login screen and forget every screen behind it. */
private fun NavHostController.backToLogin() {
    navigate(ROUTE_LOGIN) {
        popUpTo(graph.id) { inclusive = true }
    }
}
