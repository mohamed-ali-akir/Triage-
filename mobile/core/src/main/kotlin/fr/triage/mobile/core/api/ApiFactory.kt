package fr.triage.mobile.core.api

import fr.triage.mobile.core.data.TokenStore
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit

object ApiFactory {
    /** Unknown fields are ignored, so that the server can add fields without breaking the app. */
    val json: Json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
    }

    /**
     * @param baseUrl must end with a slash, e.g. "http://10.0.2.2:8000/api/v1/"
     */
    fun create(baseUrl: String, tokenStore: TokenStore): TriageApi {
        val client = OkHttpClient.Builder()
            .addInterceptor { chain ->
                val request = chain.request().newBuilder()
                    // Ask for JSON, so that errors are JSON too.
                    .header("Accept", "application/json")
                    .apply { tokenStore.token()?.let { header("Authorization", "Bearer $it") } }
                    .build()

                chain.proceed(request)
            }
            .connectTimeout(10, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .build()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(TriageApi::class.java)
    }
}
