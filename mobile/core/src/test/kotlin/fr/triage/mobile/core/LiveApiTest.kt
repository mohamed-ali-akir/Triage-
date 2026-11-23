package fr.triage.mobile.core

import fr.triage.mobile.core.api.ApiFactory
import fr.triage.mobile.core.data.ApiFailure
import fr.triage.mobile.core.data.ApiResult
import fr.triage.mobile.core.data.AuthRepository
import fr.triage.mobile.core.data.InMemoryTokenStore
import fr.triage.mobile.core.data.TicketRepository
import kotlinx.coroutines.runBlocking
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Assume.assumeTrue
import org.junit.Test

/**
 * The same client against a real Triage server, to check that the Kotlin code and
 * the Laravel API really agree. Skipped unless a server address is given:
 *
 *     php artisan migrate:fresh --seed && php artisan serve
 *     TRIAGE_API_URL=http://127.0.0.1:8000/api/v1/ ./gradlew :core:test
 *
 * It uses the demonstration account tech1@triage.test and changes demonstration data.
 */
class LiveApiTest {
    private val baseUrl = System.getenv("TRIAGE_API_URL").orEmpty()

    @Test
    fun `a technician logs in, reads tickets, changes a status, replies and logs out`() = runBlocking {
        assumeTrue("TRIAGE_API_URL is not set", baseUrl.isNotBlank())

        val tokens = InMemoryTokenStore()
        val api = ApiFactory.create(baseUrl, tokens)
        val auth = AuthRepository(api, tokens)
        val tickets = TicketRepository(api, tokens)

        // Wrong password first: refused, no token.
        val refused = auth.login("tech1@triage.test", "wrong-password", "Test JVM")
        assertTrue((refused as ApiResult.Failure).failure is ApiFailure.Invalid)
        assertFalse(auth.isLoggedIn())

        val user = (auth.login("tech1@triage.test", "password", "Test JVM") as ApiResult.Success).value
        assertEquals("technician", user.role)
        assertEquals(user.id, (auth.currentUser() as ApiResult.Success).value.id)

        val list = (tickets.assignedTickets() as ApiResult.Success).value
        assertTrue("The demo technician has assigned tickets", list.data.isNotEmpty())
        assertTrue(list.data.all { it.assignee?.id == user.id })

        // A ticket still "assigned": the server offers to start working on it.
        val assigned = list.data.first { it.status.value == "assigned" }
        val detail = (tickets.ticket(assigned.id) as ApiResult.Success).value
        assertTrue(detail.description!!.isNotBlank())
        assertEquals(listOf("in_progress"), detail.allowedStatuses.map { it.value })

        // Skipping a step is refused by the state machine, with an explanation.
        val skipped = (tickets.changeStatus(assigned.id, "resolved") as ApiResult.Failure).failure
        assertTrue(skipped is ApiFailure.Invalid)
        assertTrue(skipped.message.startsWith("Transition interdite"))

        val started = (tickets.changeStatus(assigned.id, "in_progress") as ApiResult.Success).value
        assertEquals("in_progress", started.status.value)

        val message = (tickets.reply(assigned.id, "Message envoyé par le test du client Kotlin.") as ApiResult.Success).value
        assertEquals(user.id, message.author.id)

        val after = (tickets.ticket(assigned.id) as ApiResult.Success).value
        assertTrue(after.messages.any { it.id == message.id })
        assertEquals(setOf("waiting_user", "resolved"), after.allowedStatuses.map { it.value }.toSet())

        assertTrue((tickets.ticket(999_999) as ApiResult.Failure).failure is ApiFailure.NotFound)

        auth.logout()
        assertFalse(auth.isLoggedIn())
    }
}
