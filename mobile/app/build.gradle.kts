plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    alias(libs.plugins.kotlin.compose)
}

android {
    namespace = "fr.triage.mobile"
    compileSdk = 35

    defaultConfig {
        applicationId = "fr.triage.mobile"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        // Address of the REST API, with a trailing slash. Override it without touching the code:
        //   ./gradlew :app:installDebug -Ptriage.apiBaseUrl=http://192.168.1.20:8000/api/v1/
        // The default, 10.0.2.2, is how the Android emulator reaches the computer it runs on.
        val apiBaseUrl = providers.gradleProperty("triage.apiBaseUrl").getOrElse("http://10.0.2.2:8000/api/v1/")
        buildConfigField("String", "API_BASE_URL", "\"$apiBaseUrl\"")

        // Certificate pinning (optional): fingerprints of the server certificate, comma separated.
        //   ./gradlew :app:assembleRelease -Ptriage.certificatePins=sha256/AAAA...=,sha256/BBBB...=
        val certificatePins = providers.gradleProperty("triage.certificatePins").getOrElse("")
        buildConfigField("String", "CERTIFICATE_PINS", "\"$certificatePins\"")
    }

    buildTypes {
        debug {
            // Plain HTTP is accepted in debug only, to talk to "php artisan serve".
            manifestPlaceholders["usesCleartextTraffic"] = "true"
        }
        release {
            isMinifyEnabled = false
            // A released application must use HTTPS.
            manifestPlaceholders["usesCleartextTraffic"] = "false"
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }
}

dependencies {
    implementation(project(":core"))

    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.kotlinx.coroutines.android)

    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.compose.material3)
    debugImplementation(libs.androidx.compose.ui.tooling)

    // Tests that drive the real application on an emulator or a phone.
    androidTestImplementation(platform(libs.androidx.compose.bom))
    androidTestImplementation(libs.androidx.compose.ui.test.junit4)
    androidTestImplementation(libs.androidx.test.ext.junit)
    androidTestImplementation(libs.androidx.test.runner)
    debugImplementation(libs.androidx.compose.ui.test.manifest)
}
