package fr.triage.mobile.ui.login

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import fr.triage.mobile.core.data.ApiFailure
import fr.triage.mobile.core.data.ApiResult
import fr.triage.mobile.core.data.AuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val code: String = "",
    // Shown once the server says this account uses two-factor authentication.
    val needsCode: Boolean = false,
    val isLoading: Boolean = false,
    val error: String? = null,
    val isLoggedIn: Boolean = false,
)

class LoginViewModel(
    private val authRepository: AuthRepository,
    private val deviceName: String,
) : ViewModel() {
    private val _state = MutableStateFlow(LoginUiState())
    val state: StateFlow<LoginUiState> = _state.asStateFlow()

    fun onEmailChange(value: String) = _state.update { it.copy(email = value, error = null) }

    fun onPasswordChange(value: String) = _state.update { it.copy(password = value, error = null) }

    fun onCodeChange(value: String) = _state.update { it.copy(code = value, error = null) }

    fun submit() {
        val current = _state.value

        if (current.isLoading) {
            return
        }

        if (current.email.isBlank() || current.password.isBlank()) {
            _state.update { it.copy(error = "Saisissez votre adresse e-mail et votre mot de passe.") }
            return
        }

        _state.update { it.copy(isLoading = true, error = null) }

        viewModelScope.launch {
            val result = authRepository.login(current.email, current.password, deviceName, current.code)

            _state.update { state ->
                when (result) {
                    // The password is dropped from memory as soon as the token is obtained.
                    is ApiResult.Success -> state.copy(isLoading = false, isLoggedIn = true, password = "", code = "")

                    is ApiResult.Failure -> {
                        val failure = result.failure
                        val asksForCode = failure is ApiFailure.Invalid && failure.fieldErrors.containsKey("code")

                        state.copy(
                            isLoading = false,
                            needsCode = state.needsCode || asksForCode,
                            error = failure.message,
                        )
                    }
                }
            }
        }
    }
}
