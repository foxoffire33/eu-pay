package nl.delaparra_services.apps.eupay.service

import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.hce.HcePaymentDataHolder
import nl.delaparra_services.apps.eupay.model.*
import kotlinx.coroutines.test.runTest
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.After
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import org.mockito.kotlin.*
import retrofit2.Response

class PaymentServiceTest {

    private lateinit var api: EuPayApi
    private lateinit var service: PaymentService

    private val testTokenId = "1efd8a00-0000-6000-8000-000000000001"

    @Before
    fun setup() {
        api = mock()
        service = PaymentService(api)
    }

    @After
    fun cleanup() {
        HcePaymentDataHolder.clear()
    }

    // ── provisionCard ─────────────────────────────

    @Test
    fun `provisionCard success`() = runTest {
        val response = HceProvisionResponse(testTokenId, "ACTIVE", "2026-03-01T00:00:00Z")
        whenever(api.provisionHce(any())).thenReturn(Response.success(response))

        val result = service.provisionCard("card-uuid", "device-fp")
        assertTrue(result.isSuccess)
        assertEquals(testTokenId, result.getOrNull()!!.tokenId)
    }

    @Test
    fun `provisionCard API error returns failure`() = runTest {
        whenever(api.provisionHce(any())).thenReturn(
            Response.error(400, """{"error":"Card not active"}""".toResponseBody())
        )

        val result = service.provisionCard("card-uuid", "device-fp")
        assertTrue(result.isFailure)
        assertTrue(result.exceptionOrNull() is ApiException)
        assertEquals(400, (result.exceptionOrNull() as ApiException).code)
    }

    @Test(expected = IllegalArgumentException::class)
    fun `provisionCard rejects blank cardId`() = runTest {
        service.provisionCard("", "device-fp")
    }

    @Test(expected = IllegalArgumentException::class)
    fun `provisionCard rejects blank fingerprint`() = runTest {
        service.provisionCard("card-uuid", "")
    }

    @Test
    fun `provisionCard network error returns failure`() = runTest {
        whenever(api.provisionHce(any())).thenThrow(RuntimeException("No network"))

        val result = service.provisionCard("card-uuid", "device-fp")
        assertTrue(result.isFailure)
    }

    // ── activateForPayment ────────────────────────

    @Test
    fun `activateForPayment success sets HcePaymentDataHolder`() = runTest {
        val payload = HcePaymentPayload(
            tokenId = testTokenId, dpan = "4000123456789012",
            expiryMonth = 12, expiryYear = 2028,
            sessionKey = "abc", atc = 1,
            cardScheme = "VISA", aid = "A0000000031010",
            expiresAt = "2026-03-01T00:00:00Z"
        )
        whenever(api.getHcePayload(testTokenId)).thenReturn(Response.success(payload))

        val result = service.activateForPayment(testTokenId)
        assertTrue(result.isSuccess)
        assertTrue(HcePaymentDataHolder.hasValidPayload())
        assertEquals("4000123456789012", HcePaymentDataHolder.activePayload!!.dpan)
    }

    @Test
    fun `activateForPayment failure does not set holder`() = runTest {
        whenever(api.getHcePayload(any())).thenReturn(
            Response.error(409, """{"error":"expired"}""".toResponseBody())
        )

        val result = service.activateForPayment(testTokenId)
        assertTrue(result.isFailure)
        assertFalse(HcePaymentDataHolder.hasValidPayload())
    }

    // ── refreshToken ──────────────────────────────

    @Test
    fun `refreshToken success`() = runTest {
        val response = HceRefreshResponse("new-key", "2026-04-01T00:00:00Z", 5)
        whenever(api.refreshHceToken(testTokenId)).thenReturn(Response.success(response))

        val result = service.refreshToken(testTokenId)
        assertTrue(result.isSuccess)
        assertEquals("new-key", result.getOrNull()!!.sessionKey)
        assertEquals(5, result.getOrNull()!!.atc)
    }

    // ── deactivateToken ───────────────────────────

    @Test
    fun `deactivateToken clears holder`() = runTest {
        HcePaymentDataHolder.setPayload(HcePaymentPayload(
            testTokenId, "dpan", 12, 2028, "key", 1, "VISA", "aid", "exp"
        ))
        assertTrue(HcePaymentDataHolder.hasValidPayload())

        whenever(api.deactivateHceToken(testTokenId))
            .thenReturn(Response.success(mapOf("status" to "DEACTIVATED")))

        val result = service.deactivateToken(testTokenId)
        assertTrue(result.isSuccess)
        assertFalse(HcePaymentDataHolder.hasValidPayload())
    }

    // ── utility methods ───────────────────────────

    @Test
    fun `clearActivePayment clears holder`() {
        HcePaymentDataHolder.setPayload(HcePaymentPayload(
            testTokenId, "dpan", 12, 2028, "key", 1, "VISA", "aid", "exp"
        ))
        service.clearActivePayment()
        assertFalse(service.isReadyForPayment())
    }

    @Test
    fun `isReadyForPayment returns false initially`() {
        assertFalse(service.isReadyForPayment())
    }
}
