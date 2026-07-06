<?php

namespace Tests\Libraries;

use App\Libraries\WhatsApp\Encryption;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class WhatsAppEncryptionTest extends CIUnitTestCase
{
    private Encryption $encryption;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryption = new Encryption();
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'test_access_token_EAAG12345';

        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
        $this->assertNotSame($plaintext, $encrypted);
    }

    public function testEachEncryptionProducesDifferentCiphertext(): void
    {
        $plaintext = 'same_plaintext_value';

        $enc1 = $this->encryption->encrypt($plaintext);
        $enc2 = $this->encryption->encrypt($plaintext);

        // Different random IVs → different ciphertexts
        $this->assertNotSame($enc1, $enc2);

        // Both must decrypt back to the same plaintext
        $this->assertSame($plaintext, $this->encryption->decrypt($enc1));
        $this->assertSame($plaintext, $this->encryption->decrypt($enc2));
    }

    public function testEncryptedFormatContainsThreeParts(): void
    {
        $encrypted = $this->encryption->encrypt('hello');
        $this->assertSame(3, substr_count($encrypted, ':') + 1);
    }

    public function testDecryptInvalidDataThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->encryption->decrypt('not_valid_encrypted_data');
    }

    public function testEncryptEmptyString(): void
    {
        $encrypted = $this->encryption->encrypt('');
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertSame('', $decrypted);
    }

    public function testLongTokenEncryption(): void
    {
        $longToken = str_repeat('EAAGabcdef1234567890', 10);

        $encrypted = $this->encryption->encrypt($longToken);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertSame($longToken, $decrypted);
    }

    public function testIsLegacyFormatReturnsFalseForGcm(): void
    {
        $encrypted = $this->encryption->encrypt('test');
        $this->assertFalse($this->encryption->isLegacyFormat($encrypted));
    }
}
