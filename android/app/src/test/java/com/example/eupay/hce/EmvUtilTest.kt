package com.example.eupay.hce

import org.junit.Assert.*
import org.junit.Test

class EmvUtilTest {

    // ── Hex conversion ──────────────────────────────

    @Test
    fun `hexToBytes converts correctly`() {
        val bytes = EmvUtil.hexToBytes("A0000000031010")
        assertEquals(7, bytes.size)
        assertEquals(0xA0.toByte(), bytes[0])
        assertEquals(0x10.toByte(), bytes[6])
    }

    @Test
    fun `bytesToHex converts correctly`() {
        val hex = EmvUtil.bytesToHex(byteArrayOf(0xA0.toByte(), 0x00, 0x10))
        assertEquals("A00010", hex)
    }

    @Test
    fun `hexToBytes and bytesToHex roundtrip`() {
        val original = "A0000000041010"
        val bytes = EmvUtil.hexToBytes(original)
        val hex = EmvUtil.bytesToHex(bytes)
        assertEquals(original, hex)
    }

    @Test
    fun `hexToBytes handles spaces`() {
        val bytes = EmvUtil.hexToBytes("A0 00 00 00 03 10 10")
        assertEquals(7, bytes.size)
    }

    @Test
    fun `hexToBytes empty string returns empty array`() {
        val bytes = EmvUtil.hexToBytes("")
        assertEquals(0, bytes.size)
    }

    // ── TLV encoding ────────────────────────────────

    @Test
    fun `tlv single byte tag short value`() {
        val tlv = EmvUtil.tlv(0x4F, byteArrayOf(0x01, 0x02))
        assertEquals(0x4F.toByte(), tlv[0])  // tag
        assertEquals(0x02.toByte(), tlv[1])  // length
        assertEquals(0x01.toByte(), tlv[2])  // value[0]
        assertEquals(0x02.toByte(), tlv[3])  // value[1]
    }

    @Test
    fun `tlv two byte tag`() {
        val tlv = EmvUtil.tlv(0x9F38, byteArrayOf(0xAA.toByte()))
        assertEquals(0x9F.toByte(), tlv[0])  // tag high
        assertEquals(0x38.toByte(), tlv[1])  // tag low
        assertEquals(0x01.toByte(), tlv[2])  // length
        assertEquals(0xAA.toByte(), tlv[3])  // value
    }

    @Test
    fun `tlv empty value`() {
        val tlv = EmvUtil.tlv(0x50, byteArrayOf())
        assertEquals(0x50.toByte(), tlv[0])
        assertEquals(0x00.toByte(), tlv[1])
        assertEquals(2, tlv.size) // just tag + length, no value
    }

    @Test
    fun `tlv long value uses extended length`() {
        val data = ByteArray(200) { 0x42 }
        val tlv = EmvUtil.tlv(0x70, data)
        assertEquals(0x70.toByte(), tlv[0])        // tag
        assertEquals(0x81.toByte(), tlv[1])          // long form indicator
        assertEquals(200.toByte(), tlv[2])           // actual length
        assertEquals(203, tlv.size)                   // 1(tag) + 2(len) + 200(data)
    }

    // ── concat ──────────────────────────────────────

    @Test
    fun `concat combines byte arrays`() {
        val a = byteArrayOf(1, 2)
        val b = byteArrayOf(3, 4)
        val c = byteArrayOf(5)
        val result = EmvUtil.concat(a, b, c)
        assertArrayEquals(byteArrayOf(1, 2, 3, 4, 5), result)
    }

    @Test
    fun `concat single array returns copy`() {
        val a = byteArrayOf(1, 2, 3)
        val result = EmvUtil.concat(a)
        assertArrayEquals(a, result)
    }

    // ── APDU parsing ────────────────────────────────

    @Test
    fun `getInstruction extracts INS byte`() {
        val apdu = byteArrayOf(0x00, 0xA4.toByte(), 0x04, 0x00, 0x07)
        assertEquals(0xA4.toByte(), EmvUtil.getInstruction(apdu))
    }

    @Test
    fun `getInstruction returns 0 for short APDU`() {
        assertEquals(0.toByte(), EmvUtil.getInstruction(byteArrayOf(0x00)))
    }

    @Test
    fun `getApduData extracts data field`() {
        // SELECT APDU: CLA=00 INS=A4 P1=04 P2=00 Lc=07 + 7 bytes data
        val aid = EmvUtil.hexToBytes("A0000000031010")
        val apdu = byteArrayOf(0x00, 0xA4.toByte(), 0x04, 0x00, 0x07) + aid
        val data = EmvUtil.getApduData(apdu)
        assertArrayEquals(aid, data)
    }

    @Test
    fun `getApduData returns empty for header-only APDU`() {
        val apdu = byteArrayOf(0x00, 0xA4.toByte(), 0x04, 0x00)
        val data = EmvUtil.getApduData(apdu)
        assertEquals(0, data.size)
    }

    // ── startsWith ──────────────────────────────────

    @Test
    fun `startsWith returns true for matching prefix`() {
        val data = byteArrayOf(0xA0.toByte(), 0x00, 0x00, 0x00, 0xFF.toByte())
        val prefix = byteArrayOf(0xA0.toByte(), 0x00, 0x00)
        assertTrue(EmvUtil.startsWith(data, prefix))
    }

    @Test
    fun `startsWith returns false for non-matching prefix`() {
        val data = byteArrayOf(0xA0.toByte(), 0x00)
        val prefix = byteArrayOf(0xB0.toByte(), 0x00)
        assertFalse(EmvUtil.startsWith(data, prefix))
    }

    @Test
    fun `startsWith returns false when data shorter than prefix`() {
        val data = byteArrayOf(0xA0.toByte())
        val prefix = byteArrayOf(0xA0.toByte(), 0x00)
        assertFalse(EmvUtil.startsWith(data, prefix))
    }

    // ── AID constants ───────────────────────────────

    @Test
    fun `PPSE AID matches spec`() {
        assertEquals(
            "325041592E5359532E4444463031",
            EmvUtil.bytesToHex(EmvUtil.AID_PPSE)
        )
    }

    @Test
    fun `Visa AID matches spec`() {
        assertEquals("A0000000031010", EmvUtil.bytesToHex(EmvUtil.AID_VISA))
    }

    @Test
    fun `Mastercard AID matches spec`() {
        assertEquals("A0000000041010", EmvUtil.bytesToHex(EmvUtil.AID_MASTERCARD))
    }

    // ── Status words ────────────────────────────────

    @Test
    fun `SW_OK is 9000`() {
        assertEquals(0x90.toByte(), EmvUtil.SW_OK[0])
        assertEquals(0x00.toByte(), EmvUtil.SW_OK[1])
    }

    @Test
    fun `SW_NOT_FOUND is 6A82`() {
        assertEquals(0x6A.toByte(), EmvUtil.SW_NOT_FOUND[0])
        assertEquals(0x82.toByte(), EmvUtil.SW_NOT_FOUND[1])
    }

    // ── Response builders ───────────────────────────

    @Test
    fun `buildPpseResponse ends with SW_OK`() {
        val response = EmvUtil.buildPpseResponse(EmvUtil.AID_VISA, "EU Pay")
        val lastTwo = response.copyOfRange(response.size - 2, response.size)
        assertArrayEquals(EmvUtil.SW_OK, lastTwo)
    }

    @Test
    fun `buildPpseResponse contains AID`() {
        val response = EmvUtil.buildPpseResponse(EmvUtil.AID_VISA, "EU Pay")
        val hex = EmvUtil.bytesToHex(response)
        assertTrue("PPSE response should contain Visa AID", hex.contains("A0000000031010"))
    }

    @Test
    fun `buildSelectResponse ends with SW_OK`() {
        val response = EmvUtil.buildSelectResponse(EmvUtil.AID_VISA, "VISA DEBIT")
        val lastTwo = response.copyOfRange(response.size - 2, response.size)
        assertArrayEquals(EmvUtil.SW_OK, lastTwo)
    }

    @Test
    fun `buildGpoResponse ends with SW_OK`() {
        val aip = byteArrayOf(0x19, 0x80.toByte())
        val afl = byteArrayOf(0x08, 0x01, 0x01, 0x01)
        val response = EmvUtil.buildGpoResponse(aip, afl)
        val lastTwo = response.copyOfRange(response.size - 2, response.size)
        assertArrayEquals(EmvUtil.SW_OK, lastTwo)
    }

    @Test
    fun `buildRecordResponse ends with SW_OK and contains PAN`() {
        val response = EmvUtil.buildRecordResponse(
            dpan = "4000123456789012",
            expiryYear = 2028,
            expiryMonth = 12,
            atc = 5,
            cardScheme = "VISA"
        )
        val lastTwo = response.copyOfRange(response.size - 2, response.size)
        assertArrayEquals(EmvUtil.SW_OK, lastTwo)

        val hex = EmvUtil.bytesToHex(response)
        // PAN tag 5A should be present
        assertTrue("Should contain PAN tag 5A", hex.contains("5A"))
    }

    // ── Cryptogram ──────────────────────────────────

    @Test
    fun `generateCryptogram returns 8 bytes`() {
        val sessionKey = "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
        val cryptogram = EmvUtil.generateCryptogram(sessionKey, 1, 1000L, byteArrayOf())
        assertEquals(8, cryptogram.size)
    }

    @Test
    fun `generateCryptogram is deterministic for same inputs`() {
        val key = "aabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccdd"
        val td = byteArrayOf(0x01, 0x02)
        val a = EmvUtil.generateCryptogram(key, 5, 5000L, td)
        val b = EmvUtil.generateCryptogram(key, 5, 5000L, td)
        assertArrayEquals(a, b)
    }

    @Test
    fun `generateCryptogram differs with different ATC`() {
        val key = "aabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccdd"
        val a = EmvUtil.generateCryptogram(key, 1, 1000L, byteArrayOf())
        val b = EmvUtil.generateCryptogram(key, 2, 1000L, byteArrayOf())
        assertFalse(a.contentEquals(b))
    }

    @Test
    fun `generateCryptogram differs with different amount`() {
        val key = "aabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccdd"
        val a = EmvUtil.generateCryptogram(key, 1, 1000L, byteArrayOf())
        val b = EmvUtil.generateCryptogram(key, 1, 2000L, byteArrayOf())
        assertFalse(a.contentEquals(b))
    }

    @Test
    fun `generateCryptogram differs with different session key`() {
        val key1 = "aabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccddaabbccdd"
        val key2 = "11223344112233441122334411223344112233441122334411223344112233"+"44"
        val a = EmvUtil.generateCryptogram(key1, 1, 1000L, byteArrayOf())
        val b = EmvUtil.generateCryptogram(key2, 1, 1000L, byteArrayOf())
        assertFalse(a.contentEquals(b))
    }

    @Test
    fun `buildGenerateAcResponse ends with SW_OK`() {
        val cryptogram = ByteArray(8) { it.toByte() }
        val response = EmvUtil.buildGenerateAcResponse(cryptogram, 42)
        val lastTwo = response.copyOfRange(response.size - 2, response.size)
        assertArrayEquals(EmvUtil.SW_OK, lastTwo)
    }
}
