package com.example.eupay.repository

import android.content.SharedPreferences

/**
 * Stores JWT auth token securely.
 * In production, use EncryptedSharedPreferences from security-crypto.
 */
class TokenRepository(private val prefs: SharedPreferences) {

    companion object {
        private const val KEY_JWT = "jwt_token"
        private const val KEY_USER_ID = "user_id"
    }

    fun saveToken(token: String) {
        prefs.edit().putString(KEY_JWT, token).apply()
    }

    fun getToken(): String? = prefs.getString(KEY_JWT, null)

    fun saveUserId(userId: String) {
        prefs.edit().putString(KEY_USER_ID, userId).apply()
    }

    fun getUserId(): String? = prefs.getString(KEY_USER_ID, null)

    fun clear() {
        prefs.edit().remove(KEY_JWT).remove(KEY_USER_ID).apply()
    }

    fun isLoggedIn(): Boolean = getToken() != null
}
