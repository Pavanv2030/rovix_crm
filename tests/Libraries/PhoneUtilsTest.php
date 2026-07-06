<?php

namespace Tests\Libraries;

use App\Libraries\WhatsApp\PhoneUtils;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PhoneUtilsTest extends CIUnitTestCase
{
    // ── normalize() ──────────────────────────────────────────────────────────

    public function testNormalizeStripsNonDigits(): void
    {
        $this->assertSame('15551234567', PhoneUtils::normalize('+1 (555) 123-4567'));
        $this->assertSame('442071234567', PhoneUtils::normalize('+44 20 7123 4567'));
        $this->assertSame('919876543210', PhoneUtils::normalize('+91 98765 43210'));
        $this->assertSame('971501234567', PhoneUtils::normalize('+971 50 123 4567'));
    }

    public function testNormalizeAlreadyNormalizedNumber(): void
    {
        $this->assertSame('15551234567', PhoneUtils::normalize('15551234567'));
    }

    public function testNormalizePreservesAllDigits(): void
    {
        $result = PhoneUtils::normalize('  +1-800-555-0199  ');
        $this->assertSame('18005550199', $result);
    }

    // ── isValid() ────────────────────────────────────────────────────────────

    public function testIsValidAcceptsNumbersWith10OrMoreDigits(): void
    {
        $this->assertTrue(PhoneUtils::isValid('+15551234567'));   // 11 digits
        $this->assertTrue(PhoneUtils::isValid('15551234567'));    // 11 digits
        $this->assertTrue(PhoneUtils::isValid('919876543210'));   // 12 digits
        $this->assertTrue(PhoneUtils::isValid('0000000000'));     // exactly 10 digits
    }

    public function testIsValidRejectsTooShortNumbers(): void
    {
        $this->assertFalse(PhoneUtils::isValid('123'));
        $this->assertFalse(PhoneUtils::isValid('9'));
        $this->assertFalse(PhoneUtils::isValid(''));
    }

    public function testIsValidRejectsNonNumericStrings(): void
    {
        $this->assertFalse(PhoneUtils::isValid('invalid'));
        $this->assertFalse(PhoneUtils::isValid('abc-def-ghij'));
    }

    // ── format() ─────────────────────────────────────────────────────────────

    public function testFormatIndianNumber(): void
    {
        $formatted = PhoneUtils::format('919876543210');
        $this->assertStringContainsString('91', $formatted);
        $this->assertStringContainsString('98765', $formatted);
    }

    public function testFormatContainsOriginalDigits(): void
    {
        $result = PhoneUtils::format('+442071234567');
        $this->assertStringContainsString('44', $result);
        $this->assertStringContainsString('2071234567', $result);
    }

    public function testFormatTenDigitNumber(): void
    {
        // 10-digit number — returned as-is (no country prefix to strip)
        $result = PhoneUtils::format('5551234567');
        $this->assertSame('5551234567', $result);
    }
}
