package fr.triage.mobile.ui

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp

/** A small rounded label, like the badges of the web application. */
@Composable
fun Badge(text: String, background: Color, content: Color, modifier: Modifier = Modifier) {
    Surface(color = background, contentColor = content, shape = RoundedCornerShape(50), modifier = modifier) {
        Text(
            text = text,
            style = MaterialTheme.typography.labelMedium,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 3.dp),
        )
    }
}

@Composable
fun StatusBadge(label: String) {
    Badge(label, background = Color(0xFFEEF2FF), content = Color(0xFF3730A3))
}

/** The priority value comes from the API: low, normal, high or critical. */
@Composable
fun PriorityBadge(value: String, label: String) {
    val (background, content) = when (value) {
        "critical" -> Color(0xFFFEE2E2) to Color(0xFFB91C1C)
        "high" -> Color(0xFFFFEDD5) to Color(0xFF9A3412)
        "normal" -> Color(0xFFE0F2FE) to Color(0xFF0369A1)
        else -> Color(0xFFF1F5F9) to Color(0xFF334155)
    }

    Badge(label, background, content)
}

@Composable
fun LateBadge() {
    Badge("En retard", background = Color(0xFFFEE2E2), content = Color(0xFFB91C1C))
}
