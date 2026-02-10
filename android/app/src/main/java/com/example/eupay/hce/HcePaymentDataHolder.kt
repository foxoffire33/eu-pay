package com.example.eupay.hce

import com.example.eupay.model.HcePaymentPayload

/**
 * Singleton holder for the active HCE payment payload.
 *
 * The HostApduService runs in a separate context and cannot use DI.
 * The app sets the payment payload here before the user taps,
 * and the HCE service reads it during the NFC transaction.
 *
 * Thread-safe via @Volatile + synchronized setter.
 */
object HcePaymentDataHolder {

    @Volatile
    var activePayload: HcePaymentPayload? = null
        private set

    @Volatile
    var isReady: Boolean = false
        private set

    @Synchronized
    fun setPayload(payload: HcePaymentPayload) {
        activePayload = payload
        isReady = true
    }

    @Synchronized
    fun clear() {
        activePayload = null
        isReady = false
    }

    fun hasValidPayload(): Boolean {
        return isReady && activePayload != null
    }
}
