package fr.triage.mobile.data

import android.content.Context
import fr.triage.mobile.core.data.TokenStore

/**
 * Keeps the access token in the private storage of the application: other
 * applications cannot read it. Only the token is stored, never the password.
 */
class SharedPreferencesTokenStore(context: Context) : TokenStore {
    private val preferences = context.getSharedPreferences("triage_session", Context.MODE_PRIVATE)

    override fun token(): String? = preferences.getString(KEY_TOKEN, null)

    override fun save(token: String) {
        preferences.edit().putString(KEY_TOKEN, token).apply()
    }

    override fun clear() {
        preferences.edit().remove(KEY_TOKEN).apply()
    }

    private companion object {
        const val KEY_TOKEN = "access_token"
    }
}
