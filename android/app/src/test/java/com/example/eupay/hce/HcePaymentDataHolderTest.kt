package com.example.eupay.hce

import com.example.eupay.model.HcePaymentPayload
import org.junit.After
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test

class HcePaymentDataHolderTest {

    private fun makePayload(dpan: String = "4000123456789012") = HcePaymentPayload(
        tokenId = "1efd8a00-0000-6000-8000-000000000001",
        dpan = dpan,
        expiryMonth = 12,
        expiryYear = 2028,
        sessionKey = "abcd1234",
        atc = 1,
        cardScheme = "VISA",
        aid = "A0000000031010",
        expiresAt = "2026-03-01T00:00:00Z"
    )

    @Before
    @After
    fun cleanup() {
        HcePaymentDataHolder.clear()
    }

    @Test
    fun `initially not ready`() {
        assertFalse(HcePaymentDataHolder.isReady)
        assertFalse(HcePaymentDataHolder.hasValidPayload())
        assertNull(HcePaymentDataHolder.activePayload)
    }

    @Test
    fun `setPayload makes it ready`() {
        HcePaymentDataHolder.setPayload(makePayload())
        assertTrue(HcePaymentDataHolder.isReady)
        assertTrue(HcePaymentDataHolder.hasValidPayload())
        assertNotNull(HcePaymentDataHolder.activePayload)
    }

    @Test
    fun `clear resets state`() {
        HcePaymentDataHolder.setPayload(makePayload())
        HcePaymentDataHolder.clear()
        assertFalse(HcePaymentDataHolder.isReady)
        assertNull(HcePaymentDataHolder.activePayload)
    }

    @Test
    fun `setPayload replaces previous payload`() {
        HcePaymentDataHolder.setPayload(makePayload("1111222233334444"))
        HcePaymentDataHolder.setPayload(makePayload("5555666677778888"))
        assertEquals("5555666677778888", HcePaymentDataHolder.activePayload!!.dpan)
    }

    @Test
    fun `payload data is accessible`() {
        val payload = makePayload()
        HcePaymentDataHolder.setPayload(payload)
        val active = HcePaymentDataHolder.activePayload!!
        assertEquals("VISA", active.cardScheme)
        assertEquals(1, active.atc)
        assertEquals("A0000000031010", active.aid)
    }
}
