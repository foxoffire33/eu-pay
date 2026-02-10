package nl.delaparra_services.apps.eupay.hce

import android.nfc.cardemulation.HostApduService
import android.os.Bundle
import android.util.Log

/**
 * Core NFC Host Card Emulation service.
 *
 * When the user taps their phone on a POS terminal, Android routes
 * the EMV APDU commands to this service. We respond as if we were
 * a contactless payment card, using tokenized data from the backend.
 *
 * EMV Transaction Flow:
 * 1. Terminal → SELECT PPSE → we return supported AIDs
 * 2. Terminal → SELECT AID (Visa/MC) → we return FCI with PDOL
 * 3. Terminal → GET PROCESSING OPTIONS → we return AIP + AFL
 * 4. Terminal → READ RECORD → we return tokenized card data
 * 5. Terminal → GENERATE AC → we return ARQC cryptogram
 */
class PaymentHceService : HostApduService() {

    companion object {
        private const val TAG = "PaymentHceService"
    }

    /** Tracks which state we're in during the EMV flow */
    private var selectedAid: ByteArray? = null

    override fun processCommandApdu(commandApdu: ByteArray, extras: Bundle?): ByteArray {
        if (commandApdu.size < 4) {
            Log.w(TAG, "APDU too short: ${commandApdu.size} bytes")
            return EmvUtil.SW_UNKNOWN
        }

        val ins = EmvUtil.getInstruction(commandApdu)
        Log.d(TAG, "APDU INS=0x${"%02X".format(ins)}, len=${commandApdu.size}")

        return when (ins) {
            EmvUtil.INS_SELECT -> handleSelect(commandApdu)
            EmvUtil.INS_GET_PROCESSING_OPTIONS -> handleGpo(commandApdu)
            EmvUtil.INS_READ_RECORD -> handleReadRecord(commandApdu)
            EmvUtil.INS_GENERATE_AC -> handleGenerateAc(commandApdu)
            else -> {
                Log.w(TAG, "Unknown INS: 0x${"%02X".format(ins)}")
                EmvUtil.SW_NOT_FOUND
            }
        }
    }

    override fun onDeactivated(reason: Int) {
        selectedAid = null
        val reasonStr = when (reason) {
            DEACTIVATION_LINK_LOSS -> "LINK_LOSS"
            DEACTIVATION_DESELECTED -> "DESELECTED"
            else -> "UNKNOWN($reason)"
        }
        Log.d(TAG, "HCE deactivated: $reasonStr")
    }

    /**
     * Handle SELECT command.
     * Terminal first selects PPSE, then selects the payment AID.
     */
    private fun handleSelect(apdu: ByteArray): ByteArray {
        val data = EmvUtil.getApduData(apdu)

        if (data.contentEquals(EmvUtil.AID_PPSE)) {
            Log.d(TAG, "SELECT PPSE")
            val payload = HcePaymentDataHolder.activePayload
            val aid = if (payload?.cardScheme == "MASTERCARD") {
                EmvUtil.AID_MASTERCARD
            } else {
                EmvUtil.AID_VISA
            }
            return EmvUtil.buildPpseResponse(aid, "EU Pay")
        }

        if (data.contentEquals(EmvUtil.AID_VISA) || data.contentEquals(EmvUtil.AID_MASTERCARD)) {
            selectedAid = data.copyOf()
            val label = if (data.contentEquals(EmvUtil.AID_VISA)) "VISA DEBIT" else "MASTERCARD"
            Log.d(TAG, "SELECT AID: $label")

            // PDOL: terminal country (tag 9F1A, 2 bytes) + transaction amount (tag 9F02, 6 bytes)
            val pdol = EmvUtil.hexToBytes("9F1A02" + "9F0206")
            return EmvUtil.buildSelectResponse(data, label, pdol)
        }

        Log.w(TAG, "SELECT unknown AID: ${EmvUtil.bytesToHex(data)}")
        return EmvUtil.SW_NOT_FOUND
    }

    /**
     * Handle GET PROCESSING OPTIONS.
     * Returns AIP (Application Interchange Profile) and AFL (Application File Locator).
     */
    private fun handleGpo(apdu: ByteArray): ByteArray {
        if (!HcePaymentDataHolder.hasValidPayload()) {
            Log.e(TAG, "GPO: no payment payload available")
            return EmvUtil.SW_CONDITIONS_NOT_SATISFIED
        }

        Log.d(TAG, "GET PROCESSING OPTIONS")

        // AIP: supports SDA, no cardholder verification for contactless
        val aip = byteArrayOf(0x19, 0x80.toByte())
        // AFL: SFI=1, first record=1, last record=1, #SDA records=1
        val afl = byteArrayOf(0x08, 0x01, 0x01, 0x01)

        return EmvUtil.buildGpoResponse(aip, afl)
    }

    /**
     * Handle READ RECORD — return tokenized card data.
     */
    private fun handleReadRecord(apdu: ByteArray): ByteArray {
        val payload = HcePaymentDataHolder.activePayload
        if (payload == null) {
            Log.e(TAG, "READ RECORD: no payload")
            return EmvUtil.SW_CONDITIONS_NOT_SATISFIED
        }

        Log.d(TAG, "READ RECORD: returning tokenized card data")

        return EmvUtil.buildRecordResponse(
            dpan = payload.dpan,
            expiryYear = payload.expiryYear,
            expiryMonth = payload.expiryMonth,
            atc = payload.atc,
            cardScheme = payload.cardScheme
        )
    }

    /**
     * Handle GENERATE AC — compute and return ARQC (Authorization Request Cryptogram).
     * This is the critical security step — proves the card (our app) is authentic.
     */
    private fun handleGenerateAc(apdu: ByteArray): ByteArray {
        val payload = HcePaymentDataHolder.activePayload
        if (payload == null) {
            Log.e(TAG, "GENERATE AC: no payload")
            return EmvUtil.SW_CONDITIONS_NOT_SATISFIED
        }

        Log.d(TAG, "GENERATE AC: computing ARQC with ATC=${payload.atc}")

        val terminalData = EmvUtil.getApduData(apdu)

        // Extract amount from terminal data if present (bytes 0-5 of CDOL data)
        val amount = if (terminalData.size >= 6) {
            var v = 0L
            for (i in 0..5) v = (v shl 8) or (terminalData[i].toLong() and 0xFF)
            v
        } else {
            0L
        }

        val cryptogram = EmvUtil.generateCryptogram(
            sessionKey = payload.sessionKey,
            atc = payload.atc,
            amount = amount,
            terminalData = terminalData
        )

        return EmvUtil.buildGenerateAcResponse(cryptogram, payload.atc)
    }
}
