import org.jetbrains.kotlin.gradle.dsl.JvmTarget

plugins {
    alias(libs.plugins.kotlin.jvm)
    alias(libs.plugins.kotlin.serialization)
}

java {
    sourceCompatibility = JavaVersion.VERSION_17
    targetCompatibility = JavaVersion.VERSION_17
}

kotlin {
    compilerOptions {
        jvmTarget.set(JvmTarget.JVM_17)
    }
}

dependencies {
    api(libs.retrofit)
    api(libs.kotlinx.coroutines.core)
    implementation(libs.okhttp)
    implementation(libs.retrofit.converter.serialization)
    api(libs.kotlinx.serialization.json)

    testImplementation(libs.junit)
    testImplementation(libs.okhttp.mockwebserver)
    testImplementation(libs.kotlinx.coroutines.test)
}

tasks.test {
    // The live test only runs when a server address is given: see LiveApiTest.
    environment("TRIAGE_API_URL", System.getenv("TRIAGE_API_URL") ?: "")
}
