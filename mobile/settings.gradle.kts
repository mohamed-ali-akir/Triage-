pluginManagement {
    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}

dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        google()
        mavenCentral()
    }
}

rootProject.name = "TriageMobile"

// core: plain Kotlin, talks to the REST API (no Android dependency, tested on the JVM).
// app: the Android application (screens written with Jetpack Compose).
include(":core")
include(":app")
