package com.example.eupay.hce

import android.content.Context
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import com.example.eupay.util.DeviceFingerprint
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith

/**
 * Instrumented tests running on a real Android device/emulator.
 * Tests ePrivacy consent enforcement with actual Android Context.
 */
@RunWith(AndroidJUnit4::class)
class DeviceFingerprintInstrumentedTest {

    private val context: Context
        get() = InstrumentationRegistry.getInstrumentation().targetContext

    @Test
    fun generateWithConsentReturns64CharHash() {
        val fp = DeviceFingerprint.generate(context, consentGiven = true)
        assertEquals(64, fp.length)
        assertTrue(fp.matches(Regex("[0-9a-f]{64}")))
    }

    @Test
    fun generateWithConsentIsDeterministic() {
        val a = DeviceFingerprint.generate(context, consentGiven = true)
        val b = DeviceFingerprint.generate(context, consentGiven = true)
        assertEquals(a, b)
    }

    @Test(expected = DeviceFingerprint.ConsentRequiredException::class)
    fun generateWithoutConsentThrowsEvenWithRealContext() {
        // ePrivacy Art. 5(3): must throw even when Context is available
        DeviceFingerprint.generate(context, consentGiven = false)
    }

    @Test
    fun privacyPreservingFingerprintWorksWithoutConsent() {
        // Privacy-preserving variant uses random install ID, no consent needed
        val fp = DeviceFingerprint.generatePrivacyPreserving(context)
        assertEquals(64, fp.length)
    }

    @Test
    fun privacyPreservingFingerprintIsStableAcrossCalls() {
        val a = DeviceFingerprint.generatePrivacyPreserving(context)
        val b = DeviceFingerprint.generatePrivacyPreserving(context)
        assertEquals(a, b)
    }

    @Test
    fun consentAndPrivacyPreservingFingerprintsDiffer() {
        val withConsent = DeviceFingerprint.generate(context, consentGiven = true)
        val privacyPreserving = DeviceFingerprint.generatePrivacyPreserving(context)
        assertNotEquals(
            "Full fingerprint should differ from privacy-preserving",
            withConsent, privacyPreserving
        )
    }
}
