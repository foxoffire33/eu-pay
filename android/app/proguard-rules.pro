# ── EuPay ProGuard Rules ──

# Keep HCE service — Android system calls it by class name
-keep class com.example.eupay.hce.PaymentHceService { *; }

# Keep EMV utilities used by HCE service
-keep class com.example.eupay.hce.EmvUtil { *; }
-keep class com.example.eupay.hce.HcePaymentDataHolder { *; }

# Retrofit models — Gson needs field names
-keepclassmembers class com.example.eupay.model.** { <fields>; }
-keep class com.example.eupay.model.** { *; }

# Retrofit interfaces
-keep,allowobfuscation interface com.example.eupay.api.EuPayApi

# Gson
-keepattributes Signature
-keepattributes *Annotation*
-keep class com.google.gson.** { *; }

# OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**

# Hilt
-keep class dagger.hilt.** { *; }
-keep class javax.inject.** { *; }

# Android Keystore crypto
-keep class com.example.eupay.crypto.ClientKeyManager { *; }

# Keep BuildConfig for API_BASE_URL
-keep class com.example.eupay.BuildConfig { *; }

# Kotlin coroutines
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory {}
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler {}

# Remove logging in release
-assumenosideeffects class android.util.Log {
    public static int v(...);
    public static int d(...);
    public static int i(...);
}
