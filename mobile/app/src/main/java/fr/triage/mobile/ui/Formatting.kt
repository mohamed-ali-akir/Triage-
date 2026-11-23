package fr.triage.mobile.ui

import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.time.format.DateTimeParseException

private val displayFormat: DateTimeFormatter = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm")

/** "2026-11-23T12:19:50+01:00" becomes "23/11/2026 12:19"; an unreadable date is shown as it is. */
fun formatDate(iso: String?): String {
    if (iso.isNullOrBlank()) {
        return ""
    }

    return try {
        OffsetDateTime.parse(iso).format(displayFormat)
    } catch (exception: DateTimeParseException) {
        iso
    }
}
