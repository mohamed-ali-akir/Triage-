package fr.triage.mobile.core

import fr.triage.mobile.core.api.ApiFactory
import fr.triage.mobile.core.data.InMemoryTokenStore
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class CertificatePinningTest {
    private val pin = "sha256/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="

    @Test
    fun `without a pin any trusted certificate is accepted`() {
        val client = ApiFactory.httpClient("https://support.exemple.fr/api/v1/", InMemoryTokenStore(), emptyList())

        assertTrue(client.certificatePinner.pins.isEmpty())
    }

    @Test
    fun `a pin is attached to the host of the server`() {
        val client = ApiFactory.httpClient("https://support.exemple.fr/api/v1/", InMemoryTokenStore(), listOf(pin))

        val pins = client.certificatePinner.pins

        assertEquals(1, pins.size)
        assertEquals("support.exemple.fr", pins.single().pattern)
        assertEquals("sha256", pins.single().hashAlgorithm)
    }
}
