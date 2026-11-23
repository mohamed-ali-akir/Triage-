package fr.triage.mobile

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.hasScrollToNodeAction
import androidx.compose.ui.test.hasText
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.compose.ui.test.onAllNodesWithText
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performScrollToNode
import androidx.compose.ui.test.performTextInput
import androidx.test.ext.junit.runners.AndroidJUnit4
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/**
 * Drives the real application on an emulator, against a real Triage server
 * seeded with the demonstration data (php artisan migrate:fresh --seed):
 * the technician logs in, opens a ticket and marks it as resolved.
 *
 * The emulator reaches the server of the host computer at 10.0.2.2:8000,
 * which is the default address of the debug build.
 */
@RunWith(AndroidJUnit4::class)
class TechnicianJourneyTest {
    @get:Rule
    val composeRule = createAndroidComposeRule<MainActivity>()

    @Test
    fun aTechnicianLogsInOpensATicketAndResolvesIt() {
        // 1. The application starts on the login screen.
        composeRule.onNodeWithText("Se connecter").assertIsDisplayed()

        composeRule.onNodeWithText("Adresse e-mail").performTextInput("tech1@triage.test")
        composeRule.onNodeWithText("Mot de passe").performTextInput("password")
        // The keyboard is open: bring the button into view before pressing it.
        composeRule.onNodeWithText("Se connecter").performScrollTo().performClick()

        // 2. The list of assigned tickets comes from the server.
        waitForText("Mes tickets")
        waitForText("VPN", substring = true)

        // 3. The detail shows the thread and the statuses the server allows.
        composeRule.onNodeWithText("VPN", substring = true).performClick()
        waitForText("Ticket #", substring = true)

        composeRule.onNode(hasScrollToNodeAction()).performScrollToNode(hasText("Discussion"))
        composeRule.onNodeWithText("Discussion").assertIsDisplayed()

        // 4. Marking the ticket as resolved goes through the API; the server then
        //    offers the next step ("Clos"), which proves the change was really made.
        composeRule.onNode(hasScrollToNodeAction()).performScrollToNode(hasText("Résolu"))
        composeRule.onNodeWithText("Résolu").performClick()

        waitForText("Clos")
    }

    private fun waitForText(text: String, substring: Boolean = false) {
        composeRule.waitUntil(timeoutMillis = 20_000) {
            composeRule.onAllNodesWithText(text, substring = substring).fetchSemanticsNodes().isNotEmpty()
        }
    }
}
