package fr.triage.mobile.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Same indigo as the web application.
private val TriageColors = lightColorScheme(
    primary = Color(0xFF4F46E5),
    onPrimary = Color.White,
    secondary = Color(0xFF475569),
    background = Color(0xFFF8FAFC),
    surface = Color.White,
    error = Color(0xFFDC2626),
)

@Composable
fun TriageTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = TriageColors, content = content)
}
