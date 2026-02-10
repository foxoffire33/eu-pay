package com.example.eupay.service

import com.example.eupay.api.EuPayApi
import com.example.eupay.model.*
import com.example.eupay.repository.TokenRepository
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
    private lateinit var service: AuthService

    @Before
    fun setup() {
        api = mock()
        tokenRepo = mock()
        service = AuthService(api, tokenRepo)
    }

    @Test
    fun `register success returns RegisterResponse`() = runTest {
        val response = RegisterResponse(
            id = "1efd8a00-0000-6000-8000-aaaaaaaaaaaa",
            kycStatus = "PENDING",
            publicKeyFingerprint = "sha256_abc123",
            encryption = "zero_knowledge"
        )
        whenever(api.register(any())).thenReturn(Response.success(response))

        val result = service.register(RegisterRequest(
            email = "test@test.com", password = "pass123",
            firstName = "Max", lastName = "M",
            publicKey = "MIIBIjANBgkq..."
        ))

        assertTrue(result.isSuccess)
        assertEquals("zero_knowledge", result.getOrNull()!!.encryption)
        assertEquals("PENDING", result.getOrNull()!!.kycStatus)
    }

    @Test
    fun `register failure returns error`() = runTest {
        whenever(api.register(any())).thenReturn(
            Response.error(422, """{"error":"Registration could not be completed"}""".toResponseBody())
        )

        val result = service.register(RegisterRequest(
            "a@b.com", "p", "A", "B", publicKey = "key"
        ))
        assertTrue(result.isFailure)
    }

    @Test
    fun `login success saves token`() = runTest {
        whenever(api.login(any())).thenReturn(
            Response.success(AuthResponse("jwt-token-abc123"))
        )

        val result = service.login("test@test.com", "pass")
        assertTrue(result.isSuccess)
        verify(tokenRepo).saveToken("jwt-token-abc123")
    }

    @Test
    fun `login failure does not save token`() = runTest {
        whenever(api.login(any())).thenReturn(
            Response.error(401, """{"error":"Bad credentials"}""".toResponseBody())
        )

        val result = service.login("bad@email.com", "wrong")
        assertTrue(result.isFailure)
        verify(tokenRepo, never()).saveToken(any())
    }

    @Test
    fun `getProfile success saves userId`() = runTest {
        val profile = UserProfile(
            id = "1efd8a00-0000-6000-8000-bbbbbbbbbbbb",
            kycStatus = "COMPLETED",
            hasAccount = true,
            encryptedFields = EncryptedUserFields(
                email = "encrypted_email_blob",
                firstName = "encrypted_first",
                lastName = "encrypted_last",
                phoneNumber = null,
                iban = "encrypted_iban"
            ),
            consent = ConsentInfo(
                gdpr = true,
                privacyVersion = "1.0.0",
                deviceTracking = false,
                marketing = false
            ),
            publicKeyFingerprint = "sha256_fingerprint"
        )
        whenever(api.getProfile()).thenReturn(Response.success(profile))

        val result = service.getProfile()
        assertTrue(result.isSuccess)
        verify(tokenRepo).saveUserId(profile.id)
        // Profile contains encrypted fields, not plaintext
        assertNotNull(result.getOrNull()!!.encryptedFields)
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
        whenever(api.login(any())).thenThrow(RuntimeException("Network error"))
        val result = service.login("a@b.com", "p")
        assertTrue(result.isFailure)
    }
}
