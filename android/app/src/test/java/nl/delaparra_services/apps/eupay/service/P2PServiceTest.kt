package nl.delaparra_services.apps.eupay.service

import nl.delaparra_services.apps.eupay.api.EuPayApi
import nl.delaparra_services.apps.eupay.crypto.ClientKeyManager
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import org.mockito.Mockito.mock

class P2PServiceTest {

    private lateinit var p2pService: P2PService

    @Before
    fun setup() {
        // Only testing IBAN validation — mock deps not used
        p2pService = P2PService(
            api = mock(EuPayApi::class.java),
            keyManager = mock(ClientKeyManager::class.java),
        )
    }

    // ── IBAN Validation ─────────────────────────────

    @Test
    fun `valid Dutch IBAN passes`() {
        // Rabobank test IBAN
        assertTrue(p2pService.isValidIban("NL91ABNA0417164300"))
    }

    @Test
    fun `valid Dutch IBAN with spaces passes`() {
        assertTrue(p2pService.isValidIban("NL91 ABNA 0417 1643 00"))
    }

    @Test
    fun `valid German IBAN passes`() {
        assertTrue(p2pService.isValidIban("DE89370400440532013000"))
    }

    @Test
    fun `valid French IBAN passes`() {
        assertTrue(p2pService.isValidIban("FR7630006000011234567890189"))
    }

    @Test
    fun `valid Spanish IBAN passes`() {
        assertTrue(p2pService.isValidIban("ES9121000418450200051332"))
    }

    @Test
    fun `valid Italian IBAN passes`() {
        assertTrue(p2pService.isValidIban("IT60X0542811101000000123456"))
    }

    @Test
    fun `valid Polish IBAN passes`() {
        assertTrue(p2pService.isValidIban("PL61109010140000071219812874"))
    }

    @Test
    fun `valid Swedish IBAN passes`() {
        assertTrue(p2pService.isValidIban("SE4550000000058398257466"))
    }

    @Test
    fun `case insensitive IBAN passes`() {
        assertTrue(p2pService.isValidIban("nl91abna0417164300"))
    }

    @Test
    fun `IBAN too short fails`() {
        assertFalse(p2pService.isValidIban("NL91ABNA"))
    }

    @Test
    fun `IBAN too long fails`() {
        assertFalse(p2pService.isValidIban("NL91ABNA0417164300000000000000000000"))
    }

    @Test
    fun `non-EU IBAN rejected`() {
        // US doesn't have IBANs, but test country check
        assertFalse(p2pService.isValidIban("US12345678901234567"))
    }

    @Test
    fun `UK IBAN rejected post-Brexit`() {
        assertFalse(p2pService.isValidIban("GB29NWBK60161331926819"))
    }

    @Test
    fun `invalid checksum fails`() {
        // Valid structure but wrong check digits
        assertFalse(p2pService.isValidIban("NL00ABNA0417164300"))
    }

    @Test
    fun `empty string fails`() {
        assertFalse(p2pService.isValidIban(""))
    }

    // ── EU/EEA Country Coverage ─────────────────────

    @Test
    fun `EEA countries accepted`() {
        // Norway
        assertTrue(p2pService.isValidIban("NO9386011117947"))
        // Iceland
        assertTrue(p2pService.isValidIban("IS140159260076545510730339"))
    }
}
