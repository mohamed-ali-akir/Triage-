package fr.triage.mobile.core.data

import fr.triage.mobile.core.api.MessageDto
import fr.triage.mobile.core.api.MessageRequest
import fr.triage.mobile.core.api.StatusRequest
import fr.triage.mobile.core.api.TicketDto
import fr.triage.mobile.core.api.TicketListResponse
import fr.triage.mobile.core.api.TriageApi

class TicketRepository(
    private val api: TriageApi,
    private val tokenStore: TokenStore,
) {
    /** The tickets assigned to the technician, most urgent first. */
    suspend fun assignedTickets(status: String? = null, page: Int = 1): ApiResult<TicketListResponse> =
        call { api.tickets(status, page) }

    suspend fun ticket(id: Int): ApiResult<TicketDto> =
        call { api.ticket(id).data }

    /** [status] must be one of the values the server listed in allowed_statuses. */
    suspend fun changeStatus(id: Int, status: String): ApiResult<TicketDto> =
        call { api.updateStatus(id, StatusRequest(status)).data }

    suspend fun reply(id: Int, body: String, isInternal: Boolean = false): ApiResult<MessageDto> =
        call { api.postMessage(id, MessageRequest(body.trim(), isInternal)).data }

    private suspend fun <T> call(block: suspend () -> T): ApiResult<T> =
        safeApiCall(block).alsoForgetTokenIfUnauthorized(tokenStore)
}
