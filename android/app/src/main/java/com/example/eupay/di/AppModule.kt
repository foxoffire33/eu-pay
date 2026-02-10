package com.example.eupay.di

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKeys
import com.example.eupay.BuildConfig
import com.example.eupay.api.AuthInterceptor
import com.example.eupay.api.EuPayApi
import com.example.eupay.repository.TokenRepository
import com.example.eupay.service.AuthService
import com.example.eupay.service.CardService
import com.example.eupay.service.PaymentService
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {

    @Provides
    @Singleton
    fun provideEncryptedPrefs(@ApplicationContext ctx: Context): SharedPreferences {
        val masterKey = MasterKeys.getOrCreate(MasterKeys.AES256_GCM_SPEC)
        return EncryptedSharedPreferences.create(
            "eupay_secure_prefs",
            masterKey,
            ctx,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    @Provides
    @Singleton
    fun provideTokenRepository(prefs: SharedPreferences) = TokenRepository(prefs)

    @Provides
    @Singleton
    fun provideOkHttp(tokenRepo: TokenRepository): OkHttpClient {
        return OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor(tokenRepo))
            .addInterceptor(HttpLoggingInterceptor().apply {
                level = if (BuildConfig.DEBUG)
                    HttpLoggingInterceptor.Level.BODY
                else
                    HttpLoggingInterceptor.Level.NONE
            })
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .build()
    }

    @Provides
    @Singleton
    fun provideRetrofit(client: OkHttpClient): Retrofit {
        return Retrofit.Builder()
            .baseUrl(BuildConfig.API_BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
    }

    @Provides
    @Singleton
    fun provideApi(retrofit: Retrofit): EuPayApi =
        retrofit.create(EuPayApi::class.java)

    @Provides
    @Singleton
    fun provideAuthService(api: EuPayApi, tokenRepo: TokenRepository) =
        AuthService(api, tokenRepo)

    @Provides
    @Singleton
    fun provideCardService(api: EuPayApi) = CardService(api)

    @Provides
    @Singleton
    fun providePaymentService(api: EuPayApi) = PaymentService(api)
}
