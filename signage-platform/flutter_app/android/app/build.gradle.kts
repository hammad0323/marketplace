plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.signalsignage.signage_app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "com.signalsignage"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    // Two apps from one codebase:
    //   manager → phones/tablets   flutter build apk --flavor manager -t lib/main.dart
    //   player  → Android TV/boxes flutter build apk --flavor player  -t lib/main_player.dart
    flavorDimensions += "app"
    productFlavors {
        create("manager") {
            dimension = "app"
            applicationIdSuffix = ".manager"
            resValue("string", "app_name", "Signal Signage")
        }
        create("player") {
            dimension = "app"
            applicationIdSuffix = ".player"
            resValue("string", "app_name", "Signal Player")
        }
    }

    // Release signing: create android/key.properties (see docs/DEPLOYMENT.md).
    val keyProps = java.util.Properties()
    val keyFile = rootProject.file("key.properties")
    if (keyFile.exists()) keyProps.load(java.io.FileInputStream(keyFile))
    signingConfigs {
        if (keyFile.exists()) {
            create("release") {
                keyAlias = keyProps["keyAlias"] as String
                keyPassword = keyProps["keyPassword"] as String
                storeFile = file(keyProps["storeFile"] as String)
                storePassword = keyProps["storePassword"] as String
            }
        }
    }
    buildTypes {
        release {
            signingConfig = if (keyFile.exists()) signingConfigs.getByName("release") else signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
