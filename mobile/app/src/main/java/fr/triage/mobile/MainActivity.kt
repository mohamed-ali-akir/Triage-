package fr.triage.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import fr.triage.mobile.ui.TriageNavHost
import fr.triage.mobile.ui.theme.TriageTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val container = (application as TriageApplication).container

        setContent {
            TriageTheme {
                TriageNavHost(container)
            }
        }
    }
}
