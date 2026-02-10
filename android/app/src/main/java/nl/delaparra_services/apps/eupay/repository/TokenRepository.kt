package nl.delaparra_services.apps.eupay.repository

import android.content.SharedPreferences

/**
 * Stores JWT auth token securely.
 * In production, use EncryptedSharedPreferences from security-crypto.
 */
class TokenRepository(private val prefs: SharedPreferences) {

    companion object {
        private const val KEY_JWT = "jwt_token"
        private const val KEY_USER_ID = "user_id"
        private const val KEY_HAS_PASSKEY = "has_passkey"
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

    fun setHasPasskey() {
        prefs.edit().putBoolean(KEY_HAS_PASSKEY, true).apply()
    }

    fun hasPasskey(): Boolean = prefs.getBoolean(KEY_HAS_PASSKEY, false)
}
