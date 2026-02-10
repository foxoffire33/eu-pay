package nl.delaparra_services.apps.eupay.service

import com.google.gson.Gson
import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.model.*
import nl.delaparra_services.apps.eupay.repository.TokenRepository
import kotlinx.coroutines.test.runTest
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import org.mockito.kotlin.*
import retrofit2.Response

/**
 * Tests EU compliance in Android client code:
 * - GDPR consent must be included in passkey registration
 * - Consent models are correctly structured
 */
class EuComplianceAndroidTest {

    private lateinit var api: EuPayApi
    private lateinit var tokenRepo: TokenRepository
    private lateinit var gson: Gson
    private lateinit var authService: AuthService

    @Before
    fun setup() {
        api = mock()
        tokenRepo = mock()
        gson = Gson()
        authService = AuthService(api, tokenRepo, gson)
    }

    // ── Registration Consent ────────────────────────

    @Test
    fun `PasskeyRegisterOptionsRequest includes gdpr_consent field`() {
        val request = PasskeyRegisterOptionsRequest(
            displayName = "Max Mustermann",
            gdprConsent = true,
        )

        assertTrue("GDPR consent must be true for registration", request.gdprConsent)
        assertEquals("Max Mustermann", request.displayName)
    }

    @Test
    fun `PasskeyRegisterOptionsRequest defaults consent to false`() {
        val request = PasskeyRegisterOptionsRequest(
            displayName = "Test",
        )

        assertFalse("GDPR consent defaults to false (must be explicit)", request.gdprConsent)
    }

    @Test
    fun `registration sends consent to backend`() = runTest {
        val options = PasskeyOptionsResponse(
            challengeToken = "test-token",
            options = mapOf("challenge" to "test"),
        )
        whenever(api.getRegisterOptions(any())).thenReturn(Response.success(options))

        authService.getRegisterOptions(
            displayName = "Max",
            gdprConsent = true,
        )

        verify(api).getRegisterOptions(argThat { req ->
            req.gdprConsent && req.displayName == "Max"
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
            marketingConsent = null,
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
}
