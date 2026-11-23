package fr.triage.mobile.core.data

import fr.triage.mobile.core.api.ApiErrorBody
import fr.triage.mobile.core.api.ApiFactory
import kotlinx.serialization.SerializationException
import retrofit2.HttpException
import java.io.IOException

/** The outcome of a call to the API: a value, or a failure the screen can explain. */
sealed interface ApiResult<out T> {
    data class Success<T>(val value: T) : ApiResult<T>

    data class Failure(val failure: ApiFailure) : ApiResult<Nothing>
}

sealed interface ApiFailure {
    val message: String

    /** 401: no token, or a token that was revoked or has expired. Back to the login screen. */
    data class Unauthorized(override val message: String) : ApiFailure

    /** 403: this account or this ticket does not allow the action. */
    data class Forbidden(override val message: String) : ApiFailure

    /** 404: the ticket does not exist. */
    data class NotFound(override val message: String) : ApiFailure

    /** 422: invalid input (with one message per field) or a refused business rule. */
    data class Invalid(override val message: String, val fieldErrors: Map<String, List<String>>) : ApiFailure

    /** 429: too many requests. */
    data class TooManyRequests(override val message: String) : ApiFailure

    /** No answer at all: no network, server down, timeout. */
    data class Network(override val message: String) : ApiFailure

    /** Anything else: server error or an answer the app does not understand. */
    data class Server(override val message: String) : ApiFailure
}

/**
 * Run a call and turn every exception into an [ApiFailure],
 * so that screens never have to catch anything.
 */
suspend fun <T> safeApiCall(call: suspend () -> T): ApiResult<T> =
    try {
        ApiResult.Success(call())
    } catch (exception: HttpException) {
        ApiResult.Failure(exception.toFailure())
    } catch (exception: IOException) {
        ApiResult.Failure(ApiFailure.Network("Impossible de joindre le serveur. Vérifiez votre connexion."))
    } catch (exception: SerializationException) {
        ApiResult.Failure(ApiFailure.Server("Le serveur a renvoyé une réponse inattendue."))
    }

private fun HttpException.toFailure(): ApiFailure {
    val body = errorBody()
    val message = body.message.ifBlank { "Erreur ${code()}." }

    return when (code()) {
        401 -> ApiFailure.Unauthorized("Votre session a expiré. Reconnectez-vous.")
        403 -> ApiFailure.Forbidden(message)
        404 -> ApiFailure.NotFound("Ce ticket est introuvable.")
        422 -> ApiFailure.Invalid(message, body.errors)
        429 -> ApiFailure.TooManyRequests("Trop de tentatives. Réessayez dans une minute.")
        else -> ApiFailure.Server("Le serveur a rencontré une erreur (${code()}).")
    }
}

/** Read {"message": "...", "errors": {...}}; an unreadable body gives an empty one. */
private fun HttpException.errorBody(): ApiErrorBody =
    try {
        val text = response()?.errorBody()?.string().orEmpty()
        ApiFactory.json.decodeFromString<ApiErrorBody>(text)
    } catch (exception: SerializationException) {
        ApiErrorBody()
    } catch (exception: IllegalArgumentException) {
        ApiErrorBody()
    }
