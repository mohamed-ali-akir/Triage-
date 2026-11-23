package fr.triage.mobile.core

import fr.triage.mobile.core.api.ApiFactory
import fr.triage.mobile.core.data.ApiFailure
import fr.triage.mobile.core.data.ApiResult
import fr.triage.mobile.core.data.AuthRepository
import fr.triage.mobile.core.data.InMemoryTokenStore
import fr.triage.mobile.core.data.TicketRepository
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import okhttp3.mockwebserver.SocketPolicy
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * The API client against a fake server (MockWebServer): no network, no Laravel needed.
 * The JSON bodies are the ones documented in docs/API.md.
 */
class RepositoryTest {
    private lateinit var server: MockWebServer
    private lateinit var tokens: InMemoryTokenStore
    private lateinit var auth: AuthRepository
    private lateinit var tickets: TicketRepository

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()

        tokens = InMemoryTokenStore()
        val api = ApiFactory.create(server.url("/api/v1/").toString(), tokens)
        auth = AuthRepository(api, tokens)
        tickets = TicketRepository(api, tokens)
    }

    @After
    fun tearDown() {
        server.shutdown()
    }

    private fun enqueue(code: Int, body: String = "") {
        server.enqueue(MockResponse().setResponseCode(code).setHeader("Content-Type", "application/json").setBody(body))
    }

    @Test
    fun `login sends the credentials and keeps the token`() = runTest {
        enqueue(
            200,
            """{"token":"12|abc","expires_at":"2026-12-23T12:19:48+01:00","user":{"id":2,"name":"Thomas Bernard","email":"tech1@triage.test","role":"technician"}}""",
        )

        val result = auth.login(" tech1@triage.test ", "password", "Pixel 8")

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/login", request.path)
        assertEquals("application/json", request.getHeader("Accept"))
        assertNull("No token yet at login", request.getHeader("Authorization"))
        assertEquals(
            """{"email":"tech1@triage.test","password":"password","device_name":"Pixel 8"}""",
            request.body.readUtf8(),
        )

        assertTrue(result is ApiResult.Success)
        assertEquals("Thomas Bernard", (result as ApiResult.Success).value.name)
        assertEquals("12|abc", tokens.token())
        assertTrue(auth.isLoggedIn())
    }

    @Test
    fun `the two-factor code is sent only when given`() = runTest {
        enqueue(200, """{"token":"t","expires_at":"x","user":{"id":1,"name":"A","email":"a@b.fr","role":"technician"}}""")

        auth.login("a@b.fr", "password", "Pixel 8", code = " 123456 ")

        assertTrue(server.takeRequest().body.readUtf8().contains(""""code":"123456""""))
    }

    @Test
    fun `refused credentials are reported per field and no token is kept`() = runTest {
        enqueue(
            422,
            """{"message":"Ces identifiants ne correspondent à aucun compte.","errors":{"email":["Ces identifiants ne correspondent à aucun compte."]}}""",
        )

        val result = auth.login("tech1@triage.test", "mauvais", "Pixel 8")

        val failure = (result as ApiResult.Failure).failure as ApiFailure.Invalid
        assertEquals("Ces identifiants ne correspondent à aucun compte.", failure.message)
        assertEquals(listOf("Ces identifiants ne correspondent à aucun compte."), failure.fieldErrors["email"])
        assertFalse(auth.isLoggedIn())
    }

    @Test
    fun `every request after login carries the token`() = runTest {
        tokens.save("12|abc")
        enqueue(200, """{"data":[],"meta":{"current_page":1,"last_page":1,"total":0}}""")

        tickets.assignedTickets()

        assertEquals("Bearer 12|abc", server.takeRequest().getHeader("Authorization"))
    }

    @Test
    fun `the ticket list is read with its pagination`() = runTest {
        tokens.save("t")
        enqueue(
            200,
            """
            {"data":[{"id":7,"title":"VPN en panne","status":{"value":"in_progress","label":"En cours"},
              "priority":{"value":"high","label":"Haute"},"category":{"id":2,"name":"Réseau"},
              "requester":{"id":5,"name":"Karim Benali"},"assignee":{"id":2,"name":"Thomas Bernard"},
              "is_late":true,"resolve_deadline":"2026-11-21T14:08:00+01:00",
              "created_at":"2026-11-21T06:08:00+01:00","updated_at":"2026-11-21T08:29:00+01:00"}],
             "links":{"first":"x","last":"x","prev":null,"next":null},
             "meta":{"current_page":1,"last_page":3,"per_page":20,"total":45}}
            """.trimIndent(),
        )

        val result = tickets.assignedTickets(status = "in_progress", page = 2)

        assertEquals("/api/v1/tickets?status=in_progress&page=2", server.takeRequest().path)

        val page = (result as ApiResult.Success).value
        assertEquals(45, page.meta.total)
        assertEquals(3, page.meta.lastPage)

        val ticket = page.data.single()
        assertEquals("VPN en panne", ticket.title)
        assertEquals("En cours", ticket.status.label)
        assertEquals("Haute", ticket.priority?.label)
        assertEquals("Karim Benali", ticket.requester.name)
        assertTrue(ticket.isLate)
        // The list carries no detail.
        assertNull(ticket.description)
        assertTrue(ticket.messages.isEmpty())
    }

    @Test
    fun `a ticket not triaged yet has no priority and no category`() = runTest {
        tokens.save("t")
        enqueue(
            200,
            """{"data":{"id":1,"title":"T","status":{"value":"assigned","label":"Assigné"},"priority":null,"category":null,
               "requester":{"id":5,"name":"K"},"assignee":null,"is_late":false,"resolve_deadline":null,
               "created_at":"x","updated_at":"x","description":"D","messages":[],"allowed_statuses":[],"can_reply":false}}""",
        )

        val ticket = (tickets.ticket(1) as ApiResult.Success).value

        assertNull(ticket.priority)
        assertNull(ticket.category)
        assertNull(ticket.assignee)
    }

    @Test
    fun `the detail holds the thread and the actions the server allows`() = runTest {
        tokens.save("t")
        enqueue(
            200,
            """
            {"data":{"id":10,"title":"Mot de passe oublié","status":{"value":"assigned","label":"Assigné"},
              "priority":{"value":"high","label":"Haute"},"category":{"id":3,"name":"Accès et comptes"},
              "requester":{"id":7,"name":"Marc Dubois"},"assignee":{"id":2,"name":"Thomas Bernard"},
              "is_late":false,"resolve_deadline":null,"created_at":"x","updated_at":"x",
              "description":"Compte verrouillé.",
              "messages":[{"id":15,"body":"Je regarde.","is_internal":true,"author":{"id":2,"name":"Thomas Bernard"},"created_at":"x"}],
              "allowed_statuses":[{"value":"in_progress","label":"En cours"}],"can_reply":true,
              "a_field_added_later":"ignored"}}
            """.trimIndent(),
        )

        val ticket = (tickets.ticket(10) as ApiResult.Success).value

        assertEquals("/api/v1/tickets/10", server.takeRequest().path)
        assertEquals("Compte verrouillé.", ticket.description)
        assertEquals("in_progress", ticket.allowedStatuses.single().value)
        assertTrue(ticket.canReply)
        assertTrue(ticket.messages.single().isInternal)
    }

    @Test
    fun `changing the status sends a PATCH with the chosen status`() = runTest {
        tokens.save("t")
        enqueue(
            200,
            """{"data":{"id":10,"title":"T","status":{"value":"in_progress","label":"En cours"},
               "requester":{"id":7,"name":"M"},"created_at":"x","updated_at":"x"}}""",
        )

        val result = tickets.changeStatus(10, "in_progress")

        val request = server.takeRequest()
        assertEquals("PATCH", request.method)
        assertEquals("/api/v1/tickets/10/status", request.path)
        assertEquals("""{"status":"in_progress"}""", request.body.readUtf8())
        assertEquals("En cours", (result as ApiResult.Success).value.status.label)
    }

    @Test
    fun `a transition refused by the state machine is explained`() = runTest {
        tokens.save("t")
        enqueue(422, """{"message":"Transition interdite : « Assigné » vers « Résolu »."}""")

        val failure = (tickets.changeStatus(10, "resolved") as ApiResult.Failure).failure

        assertTrue(failure is ApiFailure.Invalid)
        assertEquals("Transition interdite : « Assigné » vers « Résolu ».", failure.message)
        assertTrue((failure as ApiFailure.Invalid).fieldErrors.isEmpty())
    }

    @Test
    fun `a reply is posted and an internal note is flagged`() = runTest {
        tokens.save("t")
        val answer = """{"data":{"id":15,"body":"Fait.","is_internal":false,"author":{"id":2,"name":"T"},"created_at":"x"}}"""
        enqueue(201, answer)
        enqueue(201, answer)

        tickets.reply(10, "  Fait.  ")
        tickets.reply(10, "À vérifier.", isInternal = true)

        val public = server.takeRequest()
        assertEquals("/api/v1/tickets/10/messages", public.path)
        assertEquals("""{"body":"Fait."}""", public.body.readUtf8())
        assertEquals("""{"body":"À vérifier.","is_internal":true}""", server.takeRequest().body.readUtf8())
    }

    @Test
    fun `an expired token is forgotten so that the app shows the login screen`() = runTest {
        tokens.save("expired")
        enqueue(401, """{"message":"Unauthenticated."}""")

        val failure = (tickets.assignedTickets() as ApiResult.Failure).failure

        assertTrue(failure is ApiFailure.Unauthorized)
        assertNull(tokens.token())
    }

    @Test
    fun `http errors are mapped to a failure the screen can explain`() = runTest {
        tokens.save("t")
        enqueue(403, """{"message":"This action is unauthorized."}""")
        enqueue(404, """{"message":"No query results."}""")
        enqueue(429, """{"message":"Too Many Attempts."}""")
        enqueue(500, "<html>Server Error</html>")

        assertTrue((tickets.changeStatus(1, "resolved") as ApiResult.Failure).failure is ApiFailure.Forbidden)
        assertTrue((tickets.ticket(999) as ApiResult.Failure).failure is ApiFailure.NotFound)
        assertTrue((tickets.assignedTickets() as ApiResult.Failure).failure is ApiFailure.TooManyRequests)
        assertTrue((tickets.assignedTickets() as ApiResult.Failure).failure is ApiFailure.Server)
        // None of these means the session is over.
        assertEquals("t", tokens.token())
    }

    @Test
    fun `a server that does not answer is a network failure`() = runTest {
        tokens.save("t")
        server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AT_START))

        val failure = (tickets.assignedTickets() as ApiResult.Failure).failure

        assertTrue(failure is ApiFailure.Network)
    }

    @Test
    fun `an answer the app does not understand is not a crash`() = runTest {
        tokens.save("t")
        enqueue(200, """{"unexpected":true}""")

        assertTrue((tickets.assignedTickets() as ApiResult.Failure).failure is ApiFailure.Server)
    }

    @Test
    fun `logout revokes the token on the server and forgets it`() = runTest {
        tokens.save("12|abc")
        enqueue(204)

        auth.logout()

        val request = server.takeRequest()
        assertEquals("/api/v1/logout", request.path)
        assertEquals("Bearer 12|abc", request.getHeader("Authorization"))
        assertFalse(auth.isLoggedIn())
    }

    @Test
    fun `logout forgets the token even without network`() = runTest {
        tokens.save("12|abc")
        server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AT_START))

        auth.logout()

        assertFalse(auth.isLoggedIn())
    }
}
