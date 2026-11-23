package fr.triage.mobile.core.api

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.PATCH
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/**
 * The REST API, described as a Kotlin interface: Retrofit generates the HTTP calls.
 * One function per route of docs/API.md.
 */
interface TriageApi {
    @POST("login")
    suspend fun login(@Body body: LoginRequest): LoginResponse

    @POST("logout")
    suspend fun logout(): Response<Unit>

    @GET("me")
    suspend fun me(): MeResponse

    @GET("tickets")
    suspend fun tickets(
        @Query("status") status: String? = null,
        @Query("page") page: Int = 1,
    ): TicketListResponse

    @GET("tickets/{id}")
    suspend fun ticket(@Path("id") id: Int): TicketResponse

    @PATCH("tickets/{id}/status")
    suspend fun updateStatus(@Path("id") id: Int, @Body body: StatusRequest): TicketResponse

    @POST("tickets/{id}/messages")
    suspend fun postMessage(@Path("id") id: Int, @Body body: MessageRequest): MessageResponse
}
