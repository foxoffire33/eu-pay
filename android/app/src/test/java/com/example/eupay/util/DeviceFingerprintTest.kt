package com.example.eupay.util

import android.content.Context
import org.junit.Assert.*
import org.junit.Test
import org.mockito.kotlin.mock

class DeviceFingerprintTest {

    // ── SHA-256 (privacy-preserving hash) ───────────

    @Test
    fun `sha256 returns 64 hex chars`() {
        val hash = DeviceFingerprint.sha256("hello")
        assertEquals(64, hash.length)
        assertTrue(hash.matches(Regex("[0-9a-f]{64}")))
    }

    @Test
    fun `sha256 is deterministic`() {
        val a = DeviceFingerprint.sha256("test-input")
        val b = DeviceFingerprint.sha256("test-input")
        assertEquals(a, b)
    }

    @Test
    fun `sha256 different inputs produce different hashes`() {
        val a = DeviceFingerprint.sha256("input-1")
        val b = DeviceFingerprint.sha256("input-2")
        assertNotEquals(a, b)
    }

    @Test
    fun `sha256 known vector`() {
        val hash = DeviceFingerprint.sha256("")
        assertEquals(
            "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            hash
        )
    }

    @Test
    fun `sha256 handles unicode`() {
        val hash = DeviceFingerprint.sha256("Ünïcödé 日本語")
        assertEquals(64, hash.length)
    }

    // ── ePrivacy Directive Consent Enforcement ──────

    @Test(expected = DeviceFingerprint.ConsentRequiredException::class)
    fun `generate throws when consent not given`() {
        // ePrivacy Directive Art. 5(3): accessing device identifiers
        // requires informed consent — must throw BEFORE accessing Context
        val mockContext: Context = mock()
        DeviceFingerprint.generate(context = mockContext, consentGiven = false)
    }

    @Test
    fun `ConsentRequiredException has informative message`() {
        try {
            val mockContext: Context = mock()
            DeviceFingerprint.generate(mockContext, consentGiven = false)
            fail("Should have thrown")
        } catch (e: DeviceFingerprint.ConsentRequiredException) {
            assertTrue(e.message!!.contains("ePrivacy"))
            assertTrue(e.message!!.contains("consent"))
        }
    }

    @Test
    fun `ConsentRequiredException is IllegalStateException`() {
        val e = DeviceFingerprint.ConsentRequiredException()
        assertTrue(e is IllegalStateException)
    }

    // Note: generate() with consentGiven=true requires a real Android context
    // and is tested in androidTest/ instrumented tests.
}
