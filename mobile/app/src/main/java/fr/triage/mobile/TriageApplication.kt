package fr.triage.mobile

import android.app.Application

class TriageApplication : Application() {
    /** Created once for the whole application: see [AppContainer]. */
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
    }
}
