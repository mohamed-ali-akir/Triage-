package fr.triage.mobile.core.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// The JSON documents exchanged with the REST API (see docs/API.md).
// Field names follow the API (snake_case) through @SerialName.

@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
    @SerialName("device_name") val deviceName: String,
    // Six-digit code, only for accounts with two-factor authentication.
    val code: String? = null,
)

@Serializable
data class LoginResponse(
    val token: String,
    @SerialName("expires_at") val expiresAt: String,
    val user: UserProfile,
)

@Serializable
data class UserProfile(val id: Int, val name: String, val email: String, val role: String)

@Serializable
data class MeResponse(val user: UserProfile)

/** A value with its French label, e.g. ("in_progress", "En cours"). */
@Serializable
data class Labelled(val value: String, val label: String)

@Serializable
data class CategoryDto(val id: Int, val name: String)

@Serializable
data class PersonDto(val id: Int, val name: String)

@Serializable
data class MessageDto(
    val id: Int,
    val body: String,
    @SerialName("is_internal") val isInternal: Boolean,
    val author: PersonDto,
    @SerialName("created_at") val createdAt: String,
)

/**
 * A ticket. The list only fills the summary fields; the detail also fills
 * [description], [messages], [allowedStatuses] and [canReply].
 */
@Serializable
data class TicketDto(
    val id: Int,
    val title: String,
    val status: Labelled,
    val priority: Labelled? = null,
    val category: CategoryDto? = null,
    val requester: PersonDto,
    val assignee: PersonDto? = null,
    @SerialName("is_late") val isLate: Boolean = false,
    @SerialName("resolve_deadline") val resolveDeadline: String? = null,
    @SerialName("created_at") val createdAt: String,
    @SerialName("updated_at") val updatedAt: String,
    val description: String? = null,
    val messages: List<MessageDto> = emptyList(),
    // The server decides what is allowed: the app shows one button per entry.
    @SerialName("allowed_statuses") val allowedStatuses: List<Labelled> = emptyList(),
    @SerialName("can_reply") val canReply: Boolean = false,
)

@Serializable
data class PageMeta(
    @SerialName("current_page") val currentPage: Int,
    @SerialName("last_page") val lastPage: Int,
    val total: Int,
)

@Serializable
data class TicketListResponse(val data: List<TicketDto>, val meta: PageMeta)

@Serializable
data class TicketResponse(val data: TicketDto)

@Serializable
data class MessageResponse(val data: MessageDto)

@Serializable
data class StatusRequest(val status: String)

@Serializable
data class MessageRequest(
    val body: String,
    @SerialName("is_internal") val isInternal: Boolean = false,
)

/** The body of every error answered by the API. */
@Serializable
data class ApiErrorBody(
    val message: String = "",
    val errors: Map<String, List<String>> = emptyMap(),
)
