package com.example.eupay.api

import com.example.eupay.repository.TokenRepository
import okhttp3.Interceptor
import okhttp3.Response

/**
 * Adds Bearer JWT token to all API requests except login/register.
 */
class AuthInterceptor(
    private val tokenRepository: TokenRepository
) : Interceptor {

    private val publicPaths = listOf("/api/login_check", "/api/register")

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val path = request.url.encodedPath

        if (publicPaths.any { path.endsWith(it) }) {
            return chain.proceed(request)
        }

        val token = tokenRepository.getToken()
        if (token != null) {
            val authedRequest = request.newBuilder()
                .header("Authorization", "Bearer $token")
                .build()
            return chain.proceed(authedRequest)
        }

        return chain.proceed(request)
    }
}
