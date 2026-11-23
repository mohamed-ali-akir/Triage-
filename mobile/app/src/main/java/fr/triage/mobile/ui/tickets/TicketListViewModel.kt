package fr.triage.mobile.ui.tickets

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import fr.triage.mobile.core.api.TicketDto
import fr.triage.mobile.core.data.ApiFailure
import fr.triage.mobile.core.data.ApiResult
import fr.triage.mobile.core.data.AuthRepository
import fr.triage.mobile.core.data.TicketRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class TicketListUiState(
    val tickets: List<TicketDto> = emptyList(),
    val isLoading: Boolean = true,
    val error: String? = null,
    // The token is no longer accepted, or the user logged out: go back to the login screen.
    val loggedOut: Boolean = false,
)

class TicketListViewModel(
    private val ticketRepository: TicketRepository,
    private val authRepository: AuthRepository,
) : ViewModel() {
    private val _state = MutableStateFlow(TicketListUiState())
    val state: StateFlow<TicketListUiState> = _state.asStateFlow()

    init {
        refresh()
    }

    fun refresh() {
        _state.update { it.copy(isLoading = true, error = null) }

        viewModelScope.launch {
            val result = ticketRepository.assignedTickets()

            _state.update { state ->
                when (result) {
                    is ApiResult.Success -> state.copy(isLoading = false, tickets = result.value.data)

                    is ApiResult.Failure -> state.copy(
                        isLoading = false,
                        error = result.failure.message,
                        loggedOut = result.failure is ApiFailure.Unauthorized,
                    )
                }
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            authRepository.logout()
            _state.update { it.copy(loggedOut = true) }
        }
    }
}
