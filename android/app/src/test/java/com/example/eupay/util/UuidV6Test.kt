package com.example.eupay.util

import org.junit.Assert.*
import org.junit.Test
import java.util.UUID

class UuidV6Test {

    @Test
    fun `generate returns valid UUID format`() {
        val uuid = UuidV6.generate()
        // Should not throw
        UUID.fromString(uuid)
        assertEquals(36, uuid.length) // 8-4-4-4-12
    }

    @Test
    fun `generate returns UUIDv6 version`() {
        val uuid = UuidV6.generate()
        assertTrue("Should be valid UUIDv6", UuidV6.isValid(uuid))
    }

    @Test
    fun `generated UUIDs are unique`() {
        val uuids = (1..1000).map { UuidV6.generate() }.toSet()
        assertEquals("All 1000 UUIDs should be unique", 1000, uuids.size)
    }

    @Test
    fun `generated UUIDs are time-ordered (lexicographically sortable)`() {
        val uuids = (1..100).map {
            Thread.sleep(1)
            UuidV6.generate()
        }

        for (i in 0 until uuids.size - 1) {
            assertTrue(
                "UUID[$i] should be less than UUID[${i + 1}]: ${uuids[i]} vs ${uuids[i + 1]}",
                uuids[i] < uuids[i + 1]
            )
        }
    }

    @Test
    fun `extractTimestamp returns approximate current time`() {
        val before = System.currentTimeMillis()
        val uuid = UuidV6.generate()
        val after = System.currentTimeMillis()

        val extracted = UuidV6.extractTimestamp(uuid)

        assertTrue("Timestamp should be >= before ($before), got $extracted", extracted >= before - 100)
        assertTrue("Timestamp should be <= after ($after), got $extracted", extracted <= after + 100)
    }

    @Test
    fun `isValid returns true for valid UUIDv6`() {
        val uuid = UuidV6.generate()
        assertTrue(UuidV6.isValid(uuid))
    }

    @Test
    fun `isValid returns false for UUIDv4`() {
        val v4 = UUID.randomUUID().toString()
        assertFalse("UUIDv4 should not be valid UUIDv6", UuidV6.isValid(v4))
    }

    @Test
    fun `isValid returns false for garbage string`() {
        assertFalse(UuidV6.isValid("not-a-uuid"))
        assertFalse(UuidV6.isValid(""))
        assertFalse(UuidV6.isValid("12345"))
    }

    @Test
    fun `version nibble is 6`() {
        val uuid = UUID.fromString(UuidV6.generate())
        val version = ((uuid.mostSignificantBits ushr 12) and 0xF).toInt()
        assertEquals(6, version)
    }

    @Test
    fun `variant bits are RFC 4122`() {
        val uuid = UUID.fromString(UuidV6.generate())
        val variant = ((uuid.leastSignificantBits ushr 62) and 0x3).toInt()
        assertEquals("Variant should be 0b10 (RFC 4122)", 2, variant)
    }

    @Test
    fun `clock sequence handles same-millisecond generation`() {
        // Generate many UUIDs in tight loop (same millisecond)
        val uuids = (1..50).map { UuidV6.generate() }
        val unique = uuids.toSet()
        assertEquals("All UUIDs must be unique even in same ms", 50, unique.size)
    }

    @Test
    fun `timestamps of sequential UUIDs are non-decreasing`() {
        val timestamps = (1..20).map {
            Thread.sleep(2)
            UuidV6.extractTimestamp(UuidV6.generate())
        }

        for (i in 0 until timestamps.size - 1) {
            assertTrue(
                "Timestamps should be non-decreasing",
                timestamps[i] <= timestamps[i + 1]
            )
        }
    }
}
