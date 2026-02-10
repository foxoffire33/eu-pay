package com.example.eupay.util

import java.security.SecureRandom
import java.util.UUID

/**
 * UUIDv6 generator — time-ordered UUIDs suitable for database primary keys.
 *
 * UUIDv6 reorders the timestamp bits from UUIDv1 so that lexicographic
 * sorting equals chronological sorting, making them ideal for B-tree indexes.
 *
 * Layout (128 bits):
 *   time_high (32) | time_mid (16) | ver(4) + time_low(12) | var(2) + clock_seq(14) | node(48)
 */
object UuidV6 {

    private val random = SecureRandom()
    private var lastTimestamp = 0L
    private var clockSeq = random.nextInt(0x3FFF) // 14-bit clock sequence

    /**
     * Generate a new UUIDv6.
     */
    @Synchronized
    fun generate(): String {
        var timestamp = gregorianTimestamp()

        if (timestamp <= lastTimestamp) {
            clockSeq = (clockSeq + 1) and 0x3FFF
            if (clockSeq == 0) {
                // Clock sequence wrapped — wait for next tick
                while (timestamp <= lastTimestamp) {
                    timestamp = gregorianTimestamp()
                }
            }
        }
        lastTimestamp = timestamp

        return format(timestamp, clockSeq)
    }

    /**
     * Parse a UUIDv6 string and extract its embedded timestamp as epoch millis.
     */
    fun extractTimestamp(uuidv6: String): Long {
        val uuid = UUID.fromString(uuidv6)
        val msb = uuid.mostSignificantBits

        // Reconstruct the 60-bit Gregorian timestamp from UUIDv6 layout
        val timeHigh = (msb ushr 32) and 0xFFFFFFFFL
        val timeMid = (msb ushr 16) and 0xFFFFL
        val timeLow = msb and 0x0FFFL

        val gregorian = (timeHigh shl 28) or (timeMid shl 12) or timeLow

        // Convert from 100ns intervals since 1582-10-15 to epoch millis
        val gregorianEpochOffset = 122192928000000000L
        return (gregorian - gregorianEpochOffset) / 10_000
    }

    /**
     * Validate that a string is a valid UUIDv6 (version nibble = 6).
     */
    fun isValid(value: String): Boolean {
        return try {
            val uuid = UUID.fromString(value)
            val version = ((uuid.mostSignificantBits ushr 12) and 0xF).toInt()
            val variant = ((uuid.leastSignificantBits ushr 62) and 0x3).toInt()
            version == 6 && variant == 2
        } catch (_: Exception) {
            false
        }
    }

    // 100-nanosecond intervals since 1582-10-15 (Gregorian calendar epoch)
    private fun gregorianTimestamp(): Long {
        val epochMillis = System.currentTimeMillis()
        val gregorianEpochOffset = 122192928000000000L
        return gregorianEpochOffset + (epochMillis * 10_000)
    }

    private fun format(timestamp: Long, clockSeq: Int): String {
        // UUIDv6 bit layout
        val timeHigh = (timestamp ushr 28) and 0xFFFFFFFFL
        val timeMid = (timestamp ushr 12) and 0xFFFFL
        val timeLow = timestamp and 0xFFFL

        val msb = (timeHigh shl 32) or (timeMid shl 16) or (0x6000L) or timeLow

        // LSB: variant (10) + clock_seq (14 bits) + node (48 bits random)
        val nodeBytes = ByteArray(6)
        random.nextBytes(nodeBytes)
        var node = 0L
        for (b in nodeBytes) {
            node = (node shl 8) or (b.toLong() and 0xFF)
        }

        val lsb = (0x80L shl 56) or // variant = 10
                ((clockSeq.toLong() and 0x3FFF) shl 48) or
                (node and 0xFFFFFFFFFFFFL)

        val uuid = UUID(msb, lsb)
        return uuid.toString()
    }
}
