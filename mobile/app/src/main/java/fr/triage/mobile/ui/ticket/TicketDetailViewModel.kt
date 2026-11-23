package fr.triage.mobile.ui.ticket

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import fr.triage.mobile.core.api.TicketDto
import fr.triage.mobile.core.data.ApiFailure
import fr.triage.mobile.core.data.ApiResult
import fr.triage.mobile.core.data.TicketRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class TicketDetailUiState(
    val ticket: TicketDto? = null,
    val isLoading: Boolean = true,
    // A status change or a message is being sent.
    val isWorking: Boolean = false,
    val error: String? = null,
    val draft: String = "",
    val isInternal: Boolean = false,
    val sessionExpired: Boolean = false,
)

class TicketDetailViewModel(
    private val ticketId: Int,
    private val ticketRepository: TicketRepository,
) : ViewModel() {
    private val _state = MutableStateFlow(TicketDetailUiState())
    val state: StateFlow<TicketDetailUiState> = _state.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.update { it.copy(isLoading = true, error = null) }

        viewModelScope.launch {
            val result = ticketRepository.ticket(ticketId)

            _state.update { state ->
                when (result) {
                    is ApiResult.Success -> state.copy(isLoading = false, ticket = result.value)
                    is ApiResult.Failure -> state.failed(result.failure).copy(isLoading = false)
                }
            }
        }
    }

    fun onDraftChange(value: String) = _state.update { it.copy(draft = value, error = null) }

    fun onInternalChange(value: Boolean) = _state.update { it.copy(isInternal = value) }

    /** [status] is one of the values the server listed in allowed_statuses. */
    fun changeStatus(status: String) {
        if (_state.value.isWorking) {
            return
        }

        _state.update { it.copy(isWorking = true, error = null) }

        viewModelScope.launch {
            when (val result = ticketRepository.changeStatus(ticketId, status)) {
                // Reload the detail: the allowed actions depend on the new status.
                is ApiResult.Success -> {
                    _state.update { it.copy(isWorking = false) }
                    load()
                }

                is ApiResult.Failure -> _state.update { it.failed(result.failure).copy(isWorking = false) }
            }
        }
    }

    fun sendMessage() {
        val current = _state.value

        if (current.isWorking || current.draft.isBlank()) {
            return
        }

        _state.update { it.copy(isWorking = true, error = null) }

        viewModelScope.launch {
            when (val result = ticketRepository.reply(ticketId, current.draft, current.isInternal)) {
                is ApiResult.Success -> {
                    _state.update { it.copy(isWorking = false, draft = "", isInternal = false) }
                    load()
                }

                is ApiResult.Failure -> _state.update { it.failed(result.failure).copy(isWorking = false) }
            }
        }
    }

    private fun TicketDetailUiState.failed(failure: ApiFailure): TicketDetailUiState =
        copy(error = failure.message, sessionExpired = failure is ApiFailure.Unauthorized)
}
