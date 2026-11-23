package fr.triage.mobile.core.data

/**
 * Where the access token is kept. The password itself is never stored.
 * The Android module provides an implementation backed by the private storage of the app.
 */
interface TokenStore {
    fun token(): String?

    fun save(token: String)

    fun clear()
}

/** Keeps the token in memory only: used by the tests. */
class InMemoryTokenStore(private var token: String? = null) : TokenStore {
    override fun token(): String? = token

    override fun save(token: String) {
        this.token = token
    }

    override fun clear() {
        token = null
    }
}
