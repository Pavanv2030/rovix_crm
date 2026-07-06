<?php

namespace Tests\Libraries;

use App\Libraries\WhatsApp\WebhookSignature;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class WebhookSignatureTest extends CIUnitTestCase
{
    private string $appSecret = 'test_app_secret_rovix_12345';

    private function sign(string $payload): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);
    }

    // ── verify() ─────────────────────────────────────────────────────────────

    public function testValidSignatureVerifies(): void
    {
        $payload   = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);
        $signature = $this->sign($payload);

        $this->assertTrue(WebhookSignature::verify($payload, $signature, $this->appSecret));
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $payload = json_encode(['test' => 'data']);

        $this->assertFalse(WebhookSignature::verify($payload, 'sha256=invalid_sig', $this->appSecret));
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $original  = json_encode(['message' => 'hello']);
        $signature = $this->sign($original);
        $tampered  = json_encode(['message' => 'hacked']);

        $this->assertFalse(WebhookSignature::verify($tampered, $signature, $this->appSecret));
    }

    public function testEmptySecretIsRejected(): void
    {
        $payload = json_encode(['test' => 'data']);

        $this->assertFalse(WebhookSignature::verify($payload, $this->sign($payload), ''));
    }

    public function testEmptySignatureIsRejected(): void
    {
        $payload = json_encode(['test' => 'data']);

        $this->assertFalse(WebhookSignature::verify($payload, '', $this->appSecret));
    }

    public function testSignaturesAreConsistentForSameInput(): void
    {
        $payload = json_encode(['entry' => [['id' => '123']]]);

        $sig1 = $this->sign($payload);
        $sig2 = $this->sign($payload);

        $this->assertSame($sig1, $sig2);
        $this->assertTrue(WebhookSignature::verify($payload, $sig1, $this->appSecret));
    }

    public function testSignatureStartsWithSha256Prefix(): void
    {
        $sig = $this->sign('test payload');
        $this->assertStringStartsWith('sha256=', $sig);
        $this->assertSame(71, strlen($sig)); // "sha256=" (7) + 64 hex chars
    }
}
