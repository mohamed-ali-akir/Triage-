package fr.triage.mobile

import android.content.Context
import fr.triage.mobile.core.api.ApiFactory
import fr.triage.mobile.core.data.AuthRepository
import fr.triage.mobile.core.data.TicketRepository
import fr.triage.mobile.core.data.TokenStore
import fr.triage.mobile.data.SharedPreferencesTokenStore

/**
 * Builds the objects shared by all screens, by hand. With three objects,
 * a dependency injection library would add more than it saves.
 */
class AppContainer(context: Context) {
    private val tokenStore: TokenStore = SharedPreferencesTokenStore(context)

    private val api = ApiFactory.create(BuildConfig.API_BASE_URL, tokenStore)

    val authRepository = AuthRepository(api, tokenStore)

    val ticketRepository = TicketRepository(api, tokenStore)
}
