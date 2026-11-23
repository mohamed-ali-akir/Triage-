package fr.triage.mobile.core.data

import fr.triage.mobile.core.api.LoginRequest
import fr.triage.mobile.core.api.TriageApi
import fr.triage.mobile.core.api.UserProfile

class AuthRepository(
    private val api: TriageApi,
    private val tokenStore: TokenStore,
) {
    fun isLoggedIn(): Boolean = tokenStore.token() != null

    /**
     * Exchange the credentials for a token and keep the token.
     * The password is used once and never stored.
     */
    suspend fun login(email: String, password: String, deviceName: String, code: String? = null): ApiResult<UserProfile> {
        val result = safeApiCall {
            api.login(LoginRequest(email.trim(), password, deviceName, code?.trim()?.ifEmpty { null }))
        }

        return when (result) {
            is ApiResult.Success -> {
                tokenStore.save(result.value.token)
                ApiResult.Success(result.value.user)
            }

            is ApiResult.Failure -> result
        }
    }

    suspend fun currentUser(): ApiResult<UserProfile> =
        safeApiCall { api.me().user }.alsoForgetTokenIfUnauthorized(tokenStore)

    /**
     * Revoke the token on the server, then forget it. The token is forgotten
     * even without network: the user asked to be logged out of this phone.
     */
    suspend fun logout() {
        safeApiCall { api.logout() }
        tokenStore.clear()
    }
}

/** A token the server refuses is useless: forget it so that the app shows the login screen. */
internal fun <T> ApiResult<T>.alsoForgetTokenIfUnauthorized(tokenStore: TokenStore): ApiResult<T> {
    if (this is ApiResult.Failure && failure is ApiFailure.Unauthorized) {
        tokenStore.clear()
    }

    return this
}
