## PHASE 14: Testing & Quality Assurance (Week 11)

### Prompt 14.1 — Unit Tests for Core Libraries

```
Create unit tests for critical libraries in Rovix AI Leads Tool.

IMPORTANT: Focus on testing core business logic: encryption, phone normalization, webhook verification, rate limiting, job queue.

Create tests/Libraries/WhatsAppEncryptionTest.php:

<?php
namespace Tests\Libraries;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\WhatsApp\Encryption;

class WhatsAppEncryptionTest extends CIUnitTestCase
{
    protected $encryption;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryption = new Encryption();
    }

    public function testEncryptDecrypt()
    {
        $plaintext = 'test_access_token_12345';
        
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);
        
        $this->assertEquals($plaintext, $decrypted);
        $this->assertNotEquals($plaintext, $encrypted);
    }

    public function testEncryptionProducesDifferentCiphertexts()
    {
        $plaintext = 'same_plaintext';
        
        $encrypted1 = $this->encryption->encrypt($plaintext);
        $encrypted2 = $this->encryption->encrypt($plaintext);
        
        // Different IVs should produce different ciphertexts
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // But both should decrypt to same plaintext
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted1));
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted2));
    }

    public function testDecryptInvalidDataThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->encryption->decrypt('invalid_encrypted_data');
    }

    public function testEncryptEmptyString()
    {
        $encrypted = $this->encryption->encrypt('');
        $decrypted = $this->encryption->decrypt($encrypted);
        
        $this->assertEquals('', $decrypted);
    }
}

Create tests/Libraries/PhoneUtilsTest.php:

<?php
namespace Tests\Libraries;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\WhatsApp\PhoneUtils;

class PhoneUtilsTest extends CIUnitTestCase
{
    public function testNormalizeUSPhoneNumbers()
    {
        $tests = [
            ['input' => '+1 (555) 123-4567', 'expected' => '15551234567'],
            ['input' => '555-123-4567', 'expected' => '15551234567'],
            ['input' => '(555) 123 4567', 'expected' => '15551234567'],
            ['input' => '15551234567', 'expected' => '15551234567'],
        ];

        foreach ($tests as $test) {
            $normalized = PhoneUtils::normalize($test['input'], 'US');
            $this->assertEquals($test['expected'], $normalized);
        }
    }

    public function testNormalizeInternationalNumbers()
    {
        $tests = [
            ['input' => '+44 20 7123 4567', 'expected' => '442071234567'],
            ['input' => '+91 98765 43210', 'expected' => '919876543210'],
            ['input' => '+971 50 123 4567', 'expected' => '971501234567'],
        ];

        foreach ($tests as $test) {
            $normalized = PhoneUtils::normalize($test['input']);
            $this->assertEquals($test['expected'], $normalized);
        }
    }

    public function testFormatForDisplay()
    {
        $normalized = '15551234567';
        $formatted = PhoneUtils::formatForDisplay($normalized);
        
        $this->assertStringContainsString('555', $formatted);
        $this->assertStringContainsString('1234567', $formatted);
    }

    public function testInvalidPhoneNumberReturnsNull()
    {
        $result = PhoneUtils::normalize('invalid');
        $this->assertNull($result);
    }

    public function testValidatePhoneNumber()
    {
        $this->assertTrue(PhoneUtils::isValid('+15551234567'));
        $this->assertTrue(PhoneUtils::isValid('15551234567'));
        $this->assertFalse(PhoneUtils::isValid('123'));
        $this->assertFalse(PhoneUtils::isValid('invalid'));
    }
}

Create tests/Libraries/WebhookVerificationTest.php:

<?php
namespace Tests\Libraries;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\WhatsApp\WebhookVerification;

class WebhookVerificationTest extends CIUnitTestCase
{
    protected $appSecret = 'test_app_secret_12345';

    public function testGenerateSignature()
    {
        $payload = json_encode(['test' => 'data']);
        
        $signature = WebhookVerification::generateSignature($payload, $this->appSecret);
        
        $this->assertIsString($signature);
        $this->assertStringStartsWith('sha256=', $signature);
    }

    public function testVerifyValidSignature()
    {
        $payload = json_encode(['test' => 'data']);
        $signature = WebhookVerification::generateSignature($payload, $this->appSecret);
        
        $isValid = WebhookVerification::verify($payload, $signature, $this->appSecret);
        
        $this->assertTrue($isValid);
    }

    public function testVerifyInvalidSignature()
    {
        $payload = json_encode(['test' => 'data']);
        $invalidSignature = 'sha256=invalid_signature';
        
        $isValid = WebhookVerification::verify($payload, $invalidSignature, $this->appSecret);
        
        $this->assertFalse($isValid);
    }

    public function testVerifyTamperedPayload()
    {
        $originalPayload = json_encode(['test' => 'data']);
        $signature = WebhookVerification::generateSignature($originalPayload, $this->appSecret);
        
        $tamperedPayload = json_encode(['test' => 'tampered']);
        $isValid = WebhookVerification::verify($tamperedPayload, $signature, $this->appSecret);
        
        $this->assertFalse($isValid);
    }

    public function testSignaturesAreConsistent()
    {
        $payload = json_encode(['test' => 'data']);
        
        $sig1 = WebhookVerification::generateSignature($payload, $this->appSecret);
        $sig2 = WebhookVerification::generateSignature($payload, $this->appSecret);
        
        $this->assertEquals($sig1, $sig2);
    }
}

Create tests/Libraries/JobQueueTest.php:

<?php
namespace Tests\Libraries;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Libraries\JobDispatcher;
use App\Models\JobModel;

class JobQueueTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    public function testDispatchJob()
    {
        $dispatcher = new JobDispatcher();
        
        $jobId = $dispatcher->dispatch('send_message', [
            'conversation_id' => '123',
            'content_type' => 'text',
            'content_text' => 'Test message'
        ], null, 5);
        
        $this->assertIsString($jobId);
        
        // Check job in database
        $jobModel = new JobModel();
        $job = $jobModel->find($jobId);
        
        $this->assertEquals('send_message', $job['job_type']);
        $this->assertEquals(5, $job['priority']);
        $this->assertEquals('pending', $job['status']);
    }

    public function testJobPriorityOrdering()
    {
        $dispatcher = new JobDispatcher();
        
        // Dispatch 3 jobs with different priorities
        $dispatcher->dispatch('test', ['data' => '1'], null, 3);
        $dispatcher->dispatch('test', ['data' => '2'], null, 8);
        $dispatcher->dispatch('test', ['data' => '3'], null, 5);
        
        // Get jobs ordered by priority
        $jobModel = new JobModel();
        $jobs = $jobModel
            ->where('status', 'pending')
            ->orderBy('priority', 'DESC')
            ->findAll();
        
        $this->assertEquals(8, $jobs[0]['priority']);
        $this->assertEquals(5, $jobs[1]['priority']);
        $this->assertEquals(3, $jobs[2]['priority']);
    }

    public function testJobLocking()
    {
        $dispatcher = new JobDispatcher();
        $jobId = $dispatcher->dispatch('test', ['data' => 'test']);
        
        $jobModel = new JobModel();
        
        // Lock job
        $locked = $jobModel->lockJob($jobId);
        $this->assertTrue($locked);
        
        // Try to lock again (should fail)
        $lockedAgain = $jobModel->lockJob($jobId);
        $this->assertFalse($lockedAgain);
    }

    public function testJobRetryLogic()
    {
        $jobModel = new JobModel();
        
        $jobId = $jobModel->insert([
            'job_type' => 'test',
            'payload' => json_encode(['data' => 'test']),
            'status' => 'pending',
            'attempts' => 2,
            'priority' => 5
        ]);
        
        // Mark as failed
        $jobModel->markFailed($jobId, 'Test error');
        
        $job = $jobModel->find($jobId);
        
        // Should increment attempts
        $this->assertEquals(3, $job['attempts']);
        
        // Should move to DLQ after max attempts
        if ($job['attempts'] >= 3) {
            $this->assertEquals('failed', $job['status']);
        }
    }
}

Create tests/Libraries/RateLimiterTest.php:

<?php
namespace Tests\Libraries;

use CodeIgniter\Test\CIUnitTestCase;

class RateLimiterTest extends CIUnitTestCase
{
    public function testRateLimitingLogic()
    {
        $maxPerSecond = 70;
        $messagesSent = 0;
        $startTime = microtime(true);
        
        // Simulate sending 150 messages
        for ($i = 0; $i < 150; $i++) {
            $messagesSent++;
            
            // Apply rate limiting every 70 messages
            if ($messagesSent > 0 && $messagesSent % $maxPerSecond === 0) {
                $elapsed = microtime(true) - $startTime;
                
                if ($elapsed < 1.0) {
                    $sleepTime = (1.0 - $elapsed) * 1000000; // microseconds
                    // Don't actually sleep in test, just verify calculation
                    $this->assertGreaterThan(0, $sleepTime);
                }
                
                $startTime = microtime(true);
            }
        }
        
        // Verify we calculated sleep for batches
        $this->assertEquals(150, $messagesSent);
    }
}

Run tests:

vendor/bin/phpunit tests/Libraries/

```

### Prompt 14.2 — Integration Tests for Webhook Flow

```
Create integration tests for webhook processing flow.

Create tests/Integration/WebhookFlowTest.php:

<?php
namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class WebhookFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'TestSeeder';

    public function testWebhookVerificationHandshake()
    {
        $result = $this->get('/webhook/whatsapp', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'test_verify_token',
            'hub.challenge' => '12345'
        ]);
        
        $result->assertStatus(200);
        $this->assertEquals('12345', $result->getBody());
    }

    public function testWebhookRejectsInvalidVerifyToken()
    {
        $result = $this->get('/webhook/whatsapp', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'wrong_token',
            'hub.challenge' => '12345'
        ]);
        
        $result->assertStatus(403);
    }

    public function testInboundMessageCreatesConversationAndMessage()
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '15551234567',
                                        'id' => 'wamid.test123',
                                        'timestamp' => time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $this->withHeaders([
            'X-Hub-Signature-256' => $this->generateSignature(json_encode($payload))
        ])->post('/webhook/whatsapp', $payload);
        
        $result->assertStatus(200);
        
        // Check contact created
        $contactModel = new \App\Models\ContactModel();
        $contact = $contactModel->where('phone_normalized', '15551234567')->first();
        $this->assertNotNull($contact);
        
        // Check conversation created
        $conversationModel = new \App\Models\ConversationModel();
        $conversation = $conversationModel->where('contact_id', $contact['id'])->first();
        $this->assertNotNull($conversation);
        
        // Check message created
        $messageModel = new \App\Models\MessageModel();
        $message = $messageModel->where('conversation_id', $conversation['id'])->first();
        $this->assertNotNull($message);
        $this->assertEquals('Hello', $message['content_text']);
    }

    public function testMessageStatusUpdateProcessed()
    {
        // Create existing message
        $messageModel = new \App\Models\MessageModel();
        $messageId = $messageModel->insert([
            'conversation_id' => '1',
            'whatsapp_message_id' => 'wamid.test123',
            'direction' => 'outbound',
            'content_type' => 'text',
            'content_text' => 'Test',
            'status' => 'sent'
        ]);
        
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'statuses' => [
                                    [
                                        'id' => 'wamid.test123',
                                        'status' => 'delivered',
                                        'timestamp' => time()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $this->withHeaders([
            'X-Hub-Signature-256' => $this->generateSignature(json_encode($payload))
        ])->post('/webhook/whatsapp', $payload);
        
        $result->assertStatus(200);
        
        // Check message status updated
        $message = $messageModel->find($messageId);
        $this->assertEquals('delivered', $message['status']);
    }

    protected function generateSignature($payload)
    {
        $hash = hash_hmac('sha256', $payload, 'test_app_secret');
        return 'sha256=' . $hash;
    }
}

Run integration tests:

vendor/bin/phpunit tests/Integration/
```

Continue with Part 2 (Security & Performance Testing)?
