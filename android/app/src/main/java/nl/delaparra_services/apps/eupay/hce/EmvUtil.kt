package nl.delaparra_services.apps.eupay.hce

/**
 * EMV / APDU constants and utilities for the HCE payment service.
 *
 * EMV (Europay, Mastercard, Visa) is the protocol spoken between a
 * contactless card (our HCE service) and a POS terminal over NFC.
 */
object EmvUtil {

    // ── Status words ──────────────────────────────────
    val SW_OK = byteArrayOf(0x90.toByte(), 0x00)
    val SW_NOT_FOUND = byteArrayOf(0x6A.toByte(), 0x82.toByte())
    val SW_WRONG_LENGTH = byteArrayOf(0x67.toByte(), 0x00)
    val SW_CONDITIONS_NOT_SATISFIED = byteArrayOf(0x69.toByte(), 0x85.toByte())
    val SW_UNKNOWN = byteArrayOf(0x6F.toByte(), 0x00)

    // ── Instruction codes ─────────────────────────────
    const val INS_SELECT: Byte = 0xA4.toByte()
    const val INS_READ_RECORD: Byte = 0xB2.toByte()
    const val INS_GET_PROCESSING_OPTIONS: Byte = 0xA8.toByte()
    const val INS_GENERATE_AC: Byte = 0xAE.toByte()

    // ── AIDs ──────────────────────────────────────────
    val AID_PPSE = hexToBytes("325041592E5359532E4444463031")
    val AID_VISA = hexToBytes("A0000000031010")
    val AID_MASTERCARD = hexToBytes("A0000000041010")

    /**
     * Build a TLV (Tag-Length-Value) byte array.
     */
    fun tlv(tag: Int, value: ByteArray): ByteArray {
        val tagBytes = when {
            tag <= 0xFF -> byteArrayOf(tag.toByte())
            tag <= 0xFFFF -> byteArrayOf((tag shr 8).toByte(), (tag and 0xFF).toByte())
            else -> byteArrayOf(
                (tag shr 16).toByte(), ((tag shr 8) and 0xFF).toByte(), (tag and 0xFF).toByte()
            )
        }

        val lenBytes = when {
            value.size <= 0x7F -> byteArrayOf(value.size.toByte())
            value.size <= 0xFF -> byteArrayOf(0x81.toByte(), value.size.toByte())
            else -> byteArrayOf(
                0x82.toByte(),
                (value.size shr 8).toByte(),
                (value.size and 0xFF).toByte()
            )
        }

        return tagBytes + lenBytes + value
    }

    /**
     * Concatenate multiple TLV entries.
     */
    fun concat(vararg tlvs: ByteArray): ByteArray {
        return tlvs.fold(byteArrayOf()) { acc, it -> acc + it }
    }

    /**
     * Build a PPSE (Proximity Payment System Environment) response.
     * This is the first thing a terminal requests during a contactless transaction.
     */
    fun buildPpseResponse(aid: ByteArray, appLabel: String): ByteArray {
        val aidTlv = tlv(0x4F, aid)
        val labelTlv = tlv(0x50, appLabel.toByteArray(Charsets.US_ASCII))
        val priorityTlv = tlv(0x87, byteArrayOf(0x01))

        val appTemplate = tlv(0x61, concat(aidTlv, labelTlv, priorityTlv))
        val fciProp = tlv(0xA5, concat(
            tlv(0xBF0C, appTemplate)
        ))
        val fciFull = tlv(0x6F, concat(
            tlv(0x84, AID_PPSE),
            fciProp
        ))
        return fciFull + SW_OK
    }

    /**
     * Build FCI (File Control Information) response for SELECT AID.
     */
    fun buildSelectResponse(aid: ByteArray, appLabel: String, pdol: ByteArray? = null): ByteArray {
        val parts = mutableListOf(
            tlv(0x50, appLabel.toByteArray(Charsets.US_ASCII))
        )
        if (pdol != null) {
            parts.add(tlv(0x9F38, pdol))
        }

        val fciProp = tlv(0xA5, concat(*parts.toTypedArray()))
        val fci = tlv(0x6F, concat(tlv(0x84, aid), fciProp))
        return fci + SW_OK
    }

    /**
     * Build GET PROCESSING OPTIONS response.
     * Returns AIP (Application Interchange Profile) and AFL (Application File Locator).
     */
    fun buildGpoResponse(aip: ByteArray, afl: ByteArray): ByteArray {
        val data = aip + afl
        val response = tlv(0x80, data) // Format 1 response
        return response + SW_OK
    }

    /**
     * Build record data with card details for READ RECORD.
     */
    fun buildRecordResponse(
        dpan: String,
        expiryYear: Int,
        expiryMonth: Int,
        atc: Int,
        cardScheme: String
    ): ByteArray {
        val panBytes = dpan.chunked(2).map { it.toInt(16).toByte() }.toByteArray()
        val expiryBcd = byteArrayOf(
            (expiryYear % 100).toByte(),
            expiryMonth.toByte(),
            0x31 // day=last
        )

        val record = concat(
            tlv(0x5A, panBytes),                               // PAN
            tlv(0x5F24, expiryBcd),                            // Expiry
            tlv(0x9F27, byteArrayOf(0x80.toByte())),           // CID: ARQC
            tlv(0x9F36, byteArrayOf(                           // ATC
                (atc shr 8).toByte(), (atc and 0xFF).toByte()
            )),
            tlv(0x9F10, byteArrayOf(                           // Issuer Application Data
                0x06, 0x01, 0x0A, 0x03, 0xA4.toByte(), 0x80.toByte(), 0x00
            ))
        )

        val template = tlv(0x70, record)
        return template + SW_OK
    }

    /**
     * Generate a simple ARQC (Authorization Request Cryptogram).
     * In production, this uses the session key from the backend with HMAC-SHA256.
     */
    fun generateCryptogram(sessionKey: String, atc: Int, amount: Long, terminalData: ByteArray): ByteArray {
        val keyBytes = hexToBytes(sessionKey)
        val data = byteArrayOf(
            (atc shr 8).toByte(), (atc and 0xFF).toByte(),
            (amount shr 24).toByte(), ((amount shr 16) and 0xFF).toByte(),
            ((amount shr 8) and 0xFF).toByte(), (amount and 0xFF).toByte()
        ) + terminalData

        // HMAC-SHA256 truncated to 8 bytes = the Application Cryptogram
        val mac = javax.crypto.Mac.getInstance("HmacSHA256")
        mac.init(javax.crypto.spec.SecretKeySpec(keyBytes, "HmacSHA256"))
        val fullMac = mac.doFinal(data)
        return fullMac.copyOf(8) // AC is 8 bytes
    }

    /**
     * Build GENERATE AC response with cryptogram.
     */
    fun buildGenerateAcResponse(cryptogram: ByteArray, atc: Int): ByteArray {
        val response = concat(
            tlv(0x9F27, byteArrayOf(0x80.toByte())),           // CID: ARQC
            tlv(0x9F36, byteArrayOf(                            // ATC
                (atc shr 8).toByte(), (atc and 0xFF).toByte()
            )),
            tlv(0x9F26, cryptogram)                             // Application Cryptogram
        )
        val template = tlv(0x80, response)
        return template + SW_OK
    }

    // ── Hex utilities ─────────────────────────────────

    fun hexToBytes(hex: String): ByteArray {
        val clean = hex.replace(" ", "")
        return ByteArray(clean.length / 2) { i ->
            clean.substring(i * 2, i * 2 + 2).toInt(16).toByte()
        }
    }

    fun bytesToHex(bytes: ByteArray): String {
        return bytes.joinToString("") { "%02X".format(it) }
    }

    /**
     * Extract the instruction byte from a C-APDU.
     */
    fun getInstruction(apdu: ByteArray): Byte {
        return if (apdu.size >= 2) apdu[1] else 0
    }

    /**
     * Extract the data field from a C-APDU (bytes after the header).
     */
    fun getApduData(apdu: ByteArray): ByteArray {
        if (apdu.size <= 5) return byteArrayOf()
        val lc = apdu[4].toInt() and 0xFF
        return if (apdu.size >= 5 + lc) apdu.copyOfRange(5, 5 + lc) else byteArrayOf()
    }

    /**
     * Check if a byte array starts with another byte array.
     */
    fun startsWith(data: ByteArray, prefix: ByteArray): Boolean {
        if (data.size < prefix.size) return false
        return data.copyOfRange(0, prefix.size).contentEquals(prefix)
    }
}
