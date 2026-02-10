package nl.delaparra_services.apps.eupay.service

import com.google.gson.Gson
import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.*
import nl.delaparra_services.apps.eupay.repository.TokenRepository
import kotlinx.coroutines.test.runTest
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import org.mockito.kotlin.*
import retrofit2.Response

class AuthServiceTest {

    private lateinit var api: EuPayApi
    private lateinit var tokenRepo: TokenRepository
    private lateinit var gson: Gson
    private lateinit var service: AuthService

    @Before
    fun setup() {
        api = mock()
        tokenRepo = mock()
        gson = Gson()
        service = AuthService(api, tokenRepo, gson)
    }

    @Test
    fun `getRegisterOptions success returns options`() = runTest {
        val options = PasskeyOptionsResponse(
            challengeToken = "abc123",
            options = mapOf("challenge" to "test-challenge", "rp" to mapOf("id" to "eupay.localhost")),
        )
        whenever(api.getRegisterOptions(any())).thenReturn(Response.success(options))

        val result = service.getRegisterOptions(
            displayName = "Test User",
            gdprConsent = true,
        )

        assertTrue(result.isSuccess)
        assertEquals("abc123", result.getOrNull()!!.challengeToken)
    }

    @Test
    fun `getRegisterOptions failure returns error`() = runTest {
        whenever(api.getRegisterOptions(any())).thenReturn(
            Response.error(400, """{"error":"display_name is required"}""".toResponseBody())
        )

        val result = service.getRegisterOptions(
            displayName = "",
            gdprConsent = true,
        )
        assertTrue(result.isFailure)
    }

    @Test
    fun `completeRegistration success saves token`() = runTest {
        val registerResponse = PasskeyRegisterResponse(
            token = "jwt-token-abc",
            userId = "user-uuid-123",
        )
        whenever(api.completeRegistration(any())).thenReturn(Response.success(registerResponse))

        val credentialJson = """{"id":"cred-id","type":"public-key","rawId":"cred-id","response":{"clientDataJSON":"test","attestationObject":"test"}}"""
        val result = service.completeRegistration("challenge-token", credentialJson)

        assertTrue(result.isSuccess)
        verify(tokenRepo).saveToken("jwt-token-abc")
        verify(tokenRepo).saveUserId("user-uuid-123")
    }

    @Test
    fun `getLoginOptions success returns options`() = runTest {
        val options = PasskeyOptionsResponse(
            challengeToken = "login-challenge",
            options = mapOf("challenge" to "test-login-challenge", "rpId" to "eupay.localhost"),
        )
        whenever(api.getLoginOptions(any())).thenReturn(Response.success(options))

        val result = service.getLoginOptions()

        assertTrue(result.isSuccess)
        assertEquals("login-challenge", result.getOrNull()!!.challengeToken)
    }

    @Test
    fun `completeLogin success saves token`() = runTest {
        val loginResponse = PasskeyLoginResponse(
            token = "jwt-login-token",
            userId = "user-uuid-456",
        )
        whenever(api.completeLogin(any())).thenReturn(Response.success(loginResponse))

        val credentialJson = """{"id":"cred-id","type":"public-key","rawId":"cred-id","response":{"clientDataJSON":"test","authenticatorData":"test","signature":"test"}}"""
        val result = service.completeLogin("challenge-token", credentialJson)

        assertTrue(result.isSuccess)
        verify(tokenRepo).saveToken("jwt-login-token")
        verify(tokenRepo).saveUserId("user-uuid-456")
    }

    @Test
    fun `completeLogin failure does not save token`() = runTest {
        whenever(api.completeLogin(any())).thenReturn(
            Response.error(401, """{"error":"Authentication failed"}""".toResponseBody())
        )

        val credentialJson = """{"id":"bad","type":"public-key","rawId":"bad","response":{}}"""
        val result = service.completeLogin("bad-token", credentialJson)

        assertTrue(result.isFailure)
        verify(tokenRepo, never()).saveToken(any())
    }

    @Test
    fun `getProfile success saves userId`() = runTest {
        val profile = UserProfile(
            id = "user-uuid-789",
            displayName = "Test User",
            encryptedEmail = "encrypted_email",
            encryptedFirstName = "encrypted_first",
            encryptedLastName = "encrypted_last",
            publicKey = "key",
            hasBankAccount = true,
            createdAt = "2026-01-01T00:00:00+00:00",
            gdprConsentAt = "2026-01-01T00:00:00+00:00",
        )
        whenever(api.getProfile()).thenReturn(Response.success(profile))

        val result = service.getProfile()
        assertTrue(result.isSuccess)
        verify(tokenRepo).saveUserId(profile.id)
    }

    @Test
    fun `logout clears token repo`() {
        service.logout()
        verify(tokenRepo).clear()
    }

    @Test
    fun `isLoggedIn delegates to token repo`() {
        whenever(tokenRepo.isLoggedIn()).thenReturn(true)
        assertTrue(service.isLoggedIn())

        whenever(tokenRepo.isLoggedIn()).thenReturn(false)
        assertFalse(service.isLoggedIn())
    }

    @Test
    fun `network exception returns failure`() = runTest {
        whenever(api.getLoginOptions(any())).thenThrow(RuntimeException("Network error"))
        val result = service.getLoginOptions()
        assertTrue(result.isFailure)
    }
}
