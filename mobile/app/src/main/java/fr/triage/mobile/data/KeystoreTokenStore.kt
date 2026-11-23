package fr.triage.mobile.data

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import fr.triage.mobile.core.data.TokenStore
import java.security.GeneralSecurityException
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

/**
 * Keeps the access token in the private storage of the application, encrypted
 * with a key held by the Android Keystore: the key never leaves the secure
 * hardware of the phone, so a copy of the storage alone gives nothing.
 * Only the token is stored, never the password.
 */
class KeystoreTokenStore(context: Context) : TokenStore {
    private val preferences = context.getSharedPreferences("triage_session", Context.MODE_PRIVATE)

    // The interceptor reads the token on every request: decrypt it once.
    @Volatile
    private var cachedToken: String? = null

    override fun token(): String? {
        cachedToken?.let { return it }

        val stored = preferences.getString(KEY_TOKEN, null) ?: return null

        return try {
            decrypt(stored).also { cachedToken = it }
        } catch (exception: GeneralSecurityException) {
            // Unreadable (key lost, value written by an older version): ask for a new login.
            clear()
            null
        } catch (exception: IllegalArgumentException) {
            clear()
            null
        }
    }

    override fun save(token: String) {
        preferences.edit().putString(KEY_TOKEN, encrypt(token)).apply()
        cachedToken = token
    }

    override fun clear() {
        preferences.edit().remove(KEY_TOKEN).apply()
        cachedToken = null
    }

    private fun encrypt(token: String): String {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, secretKey())

        // The random initialisation vector is stored in front of the encrypted token.
        return Base64.encodeToString(cipher.iv + cipher.doFinal(token.toByteArray(Charsets.UTF_8)), Base64.NO_WRAP)
    }

    private fun decrypt(stored: String): String {
        val bytes = Base64.decode(stored, Base64.NO_WRAP)

        if (bytes.size <= IV_LENGTH) {
            throw IllegalArgumentException("Stored token is too short")
        }

        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.DECRYPT_MODE, secretKey(), GCMParameterSpec(TAG_LENGTH_BITS, bytes, 0, IV_LENGTH))

        return String(cipher.doFinal(bytes, IV_LENGTH, bytes.size - IV_LENGTH), Charsets.UTF_8)
    }

    private fun secretKey(): SecretKey {
        val keyStore = KeyStore.getInstance(KEYSTORE).apply { load(null) }

        (keyStore.getKey(KEY_ALIAS, null) as? SecretKey)?.let { return it }

        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, KEYSTORE)
        generator.init(
            KeyGenParameterSpec.Builder(KEY_ALIAS, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .build(),
        )

        return generator.generateKey()
    }

    private companion object {
        const val KEY_TOKEN = "access_token"
        const val KEY_ALIAS = "triage_access_token"
        const val KEYSTORE = "AndroidKeyStore"
        const val TRANSFORMATION = "AES/GCM/NoPadding"
        const val IV_LENGTH = 12
        const val TAG_LENGTH_BITS = 128
    }
}
