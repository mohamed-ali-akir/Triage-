package fr.triage.mobile

import android.content.Context
import fr.triage.mobile.core.api.ApiFactory
import fr.triage.mobile.core.data.AuthRepository
import fr.triage.mobile.core.data.TicketRepository
import fr.triage.mobile.core.data.TokenStore
import fr.triage.mobile.data.KeystoreTokenStore

/**
 * Builds the objects shared by all screens, by hand. With three objects,
 * a dependency injection library would add more than it saves.
 */
class AppContainer(context: Context) {
    private val tokenStore: TokenStore = KeystoreTokenStore(context)

    // Optional certificate pinning, chosen when the application is built (see app/build.gradle.kts).
    private val certificatePins = BuildConfig.CERTIFICATE_PINS.split(',').filter { it.isNotBlank() }

    private val api = ApiFactory.create(BuildConfig.API_BASE_URL, tokenStore, certificatePins)

    val authRepository = AuthRepository(api, tokenStore)

    val ticketRepository = TicketRepository(api, tokenStore)
}
