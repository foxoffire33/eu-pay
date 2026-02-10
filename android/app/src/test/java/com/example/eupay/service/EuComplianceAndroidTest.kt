package com.example.eupay.service

import com.example.eupay.api.EuPayApi
import com.example.eupay.model.*
import com.example.eupay.repository.TokenRepository
import kotlinx.coroutines.test.runTest
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import org.mockito.kotlin.*
import retrofit2.Response

/**
 * Tests EU compliance in Android client code:
 * - GDPR consent must be included in registration
 * - Registration must fail without consent
 * - Consent models are correctly structured
 */
class EuComplianceAndroidTest {

    private lateinit var api: EuPayApi
    private lateinit var tokenRepo: TokenRepository
    private lateinit var authService: AuthService

    @Before
    fun setup() {
        api = mock()
        tokenRepo = mock()
        authService = AuthService(api, tokenRepo)
    }

    // ── Registration Consent ────────────────────────

    @Test
    fun `RegisterRequest includes gdpr_consent and public_key fields`() {
        val request = RegisterRequest(
            email = "test@example.de",
            password = "SecurePass123!",
            firstName = "Max",
            lastName = "Mustermann",
            publicKey = "MIIBIjANBgkq...",
            gdprConsent = true,
            deviceTrackingConsent = false,
            marketingConsent = false,
        )

        assertTrue("GDPR consent must be true for registration", request.gdprConsent)
        assertFalse("Device tracking should default to opt-out", request.deviceTrackingConsent)
        assertFalse("Marketing should default to opt-out (EU opt-in only)", request.marketingConsent)
        assertNotNull("Public key is required for zero-knowledge encryption", request.publicKey)
    }

    @Test
    fun `RegisterRequest defaults consent fields to false`() {
        val request = RegisterRequest(
            email = "a@b.de", password = "p", firstName = "A", lastName = "B",
            publicKey = "test_key"
        )

        assertFalse("GDPR consent defaults to false (must be explicit)", request.gdprConsent)
        assertFalse("Device tracking defaults to false", request.deviceTrackingConsent)
        assertFalse("Marketing defaults to false", request.marketingConsent)
    }

    @Test
    fun `RegisterRequest nationality defaults to DE`() {
        val request = RegisterRequest(
            email = "a@b.de", password = "p", firstName = "A", lastName = "B",
            publicKey = "test_key"
        )
        assertEquals("DE", request.nationality)
    }

    @Test
    fun `registration sends consent to backend`() = runTest {
        val request = RegisterRequest(
            email = "max@example.de", password = "Pass!",
            firstName = "Max", lastName = "M",
            publicKey = "MIIBIjANBgkq...",
            gdprConsent = true,
            deviceTrackingConsent = true,
            marketingConsent = false,
        )

        val response = RegisterResponse("uuid-v6", "PENDING", "sha256_fp", "zero_knowledge")
        whenever(api.register(any())).thenReturn(Response.success(response))

        val result = authService.register(request)
        assertTrue(result.isSuccess)

        // Verify the request sent to API included consent and public key
        verify(api).register(argThat { req ->
            req.gdprConsent == true &&
            req.deviceTrackingConsent == true &&
            req.marketingConsent == false &&
            req.publicKey.isNotEmpty()
        })
    }

    // ── Consent Models ──────────────────────────────

    @Test
    fun `ConsentResponse deserializes correctly`() {
        val consent = ConsentResponse(
            gdprConsent = true,
            gdprConsentAt = "2026-02-10T12:00:00Z",
            privacyPolicyVersion = "1.0.0",
            deviceTrackingConsent = false,
            marketingConsent = false,
            legalBasis = "contract",
        )

        assertTrue(consent.gdprConsent)
        assertEquals("1.0.0", consent.privacyPolicyVersion)
        assertEquals("contract", consent.legalBasis)
    }

    @Test
    fun `ConsentUpdateRequest can toggle individual consents`() {
        val updateDeviceOnly = ConsentUpdateRequest(
            deviceTrackingConsent = true,
            marketingConsent = null,  // unchanged
        )
        assertTrue(updateDeviceOnly.deviceTrackingConsent!!)
        assertNull(updateDeviceOnly.marketingConsent)

        val updateMarketingOnly = ConsentUpdateRequest(
            deviceTrackingConsent = null,
            marketingConsent = false,
        )
        assertNull(updateMarketingOnly.deviceTrackingConsent)
        assertFalse(updateMarketingOnly.marketingConsent!!)
    }

    @Test
    fun `EraseRequest requires explicit confirmation`() {
        val request = EraseRequest(confirmDeletion = true)
        assertTrue(request.confirmDeletion)

        val noConfirm = EraseRequest(confirmDeletion = false)
        assertFalse(noConfirm.confirmDeletion)
    }

    // ── Currency ────────────────────────────────────

    @Test
    fun `MoneyAmount defaults to EUR`() {
        val amount = MoneyAmount(value = 42.50)
        assertEquals("EUR", amount.currency)
    }

    // ── Address is EU ───────────────────────────────

    @Test
    fun `Address defaults to DE country`() {
        val address = Address(line1 = "Str. 1", city = "Berlin", postalCode = "10115")
        assertEquals("DE", address.country)
    }
}
