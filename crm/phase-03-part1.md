## PHASE 3: WhatsApp Core Integration (Week 2-3)

### Prompt 3.1 — Encryption + Phone Utils + Webhook Signature

```
Port the WhatsApp security layer for Rovix AI Leads Tool.

Reference original wacrm files:
- src/lib/whatsapp/encryption.ts
- src/lib/whatsapp/webhook-signature.ts
- src/lib/whatsapp/phone-utils.ts

Create app/Libraries/WhatsApp/Encryption.php:

<?php
namespace App\Libraries\WhatsApp;

class Encryption
{
    private string $key;

    public function __construct()
    {
        $config = config('Rovix');
        $keyHex = $config->encryptionKey;
        
        if (strlen($keyHex) !== 64) {
            throw new \Exception('Encryption key must be 64 hex characters (32 bytes)');
        }
        
        $this->key = hex2bin($keyHex);
    }

    /**
     * Encrypt plaintext using AES-256-GCM
     * Returns: iv:ciphertext:tag (all hex-encoded)
     */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // Tag length
        );
        
        if ($ciphertext === false) {
            throw new \Exception('Encryption failed');
        }
        
        return bin2hex($iv) . ':' . bin2hex($ciphertext) . ':' . bin2hex($tag);
    }

    /**
     * Decrypt encrypted string
     * Supports both GCM (new) and CBC (legacy) formats for backward compatibility
     */
    public function decrypt(string $encrypted): string
    {
        $parts = explode(':', $encrypted);
        
        if (count($parts) === 3) {
            // GCM format: iv:ciphertext:tag
            return $this->decryptGcm($parts);
        } elseif (count($parts) === 2) {
            // Legacy CBC format: iv:ciphertext
            return $this->decryptCbc($parts);
        } else {
            throw new \Exception('Invalid encrypted format');
        }
    }

    private function decryptGcm(array $parts): string
    {
        [$ivHex, $ciphertextHex, $tagHex] = $parts;
        
        $iv = hex2bin($ivHex);
        $ciphertext = hex2bin($ciphertextHex);
        $tag = hex2bin($tagHex);
        
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($plaintext === false) {
            throw new \Exception('Decryption failed - data may be corrupted');
        }
        
        return $plaintext;
    }

    private function decryptCbc(array $parts): string
    {
        [$ivHex, $ciphertextHex] = $parts;
        
        $iv = hex2bin($ivHex);
        $ciphertext = hex2bin($ciphertextHex);
        
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($plaintext === false) {
            throw new \Exception('Decryption failed');
        }
        
        return $plaintext;
    }

    public function isLegacyFormat(string $encrypted): bool
    {
        return count(explode(':', $encrypted)) === 2;
    }
}

Create app/Libraries/WhatsApp/WebhookSignature.php:

<?php
namespace App\Libraries\WhatsApp;

class WebhookSignature
{
    /**
     * Verify Meta webhook signature
     * 
     * @param string $rawBody The raw request body (JSON string)
     * @param string $signature The X-Hub-Signature-256 header value
     * @param string $appSecret Your Meta app secret
     * @return bool
     */
    public static function verify(string $rawBody, string $signature, string $appSecret): bool
    {
        if (empty($signature) || empty($appSecret)) {
            return false;
        }
        
        // Compute expected signature
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        
        // Timing-safe comparison
        return hash_equals($expectedSignature, $signature);
    }
}

Create app/Filters/WebhookSignatureFilter.php:

<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Libraries\WhatsApp\WebhookSignature;

class WebhookSignatureFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Get raw body
        $rawBody = file_get_contents('php://input');
        
        // Get signature header
        $signature = $request->getHeaderLine('X-Hub-Signature-256');
        
        // Get app secret from config
        $config = config('WhatsApp');
        $appSecret = $config->metaAppSecret;
        
        // Fail closed if no secret configured
        if (empty($appSecret)) {
            log_message('error', 'Webhook signature verification failed: META_APP_SECRET not configured');
            return service('response')->setStatusCode(403)->setJSON([
                'error' => 'Webhook signature verification failed'
            ]);
        }
        
        // Verify signature
        if (!WebhookSignature::verify($rawBody, $signature, $appSecret)) {
            log_message('error', 'Webhook signature verification failed: invalid signature');
            return service('response')->setStatusCode(403)->setJSON([
                'error' => 'Invalid signature'
            ]);
        }
        
        // Signature valid, pass through
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed
    }
}

Create app/Libraries/WhatsApp/PhoneUtils.php:

<?php
namespace App\Libraries\WhatsApp;

class PhoneUtils
{
    /**
     * Normalize phone number - strip everything except digits
     */
    public static function normalize(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Check if phone number is valid (at least 10 digits)
     */
    public static function isValid(string $phone): bool
    {
        $normalized = self::normalize($phone);
        return strlen($normalized) >= 10;
    }

    /**
     * Format phone number for display with country code
     */
    public static function format(string $phone): string
    {
        $normalized = self::normalize($phone);
        
        // If starts with 91 (India), format as +91 XXXXX XXXXX
        if (str_starts_with($normalized, '91') && strlen($normalized) === 12) {
            return '+91 ' . substr($normalized, 2, 5) . ' ' . substr($normalized, 7);
        }
        
        // Generic format: +[country] [rest]
        if (strlen($normalized) > 10) {
            $country = substr($normalized, 0, -10);
            $rest = substr($normalized, -10);
            return '+' . $country . ' ' . $rest;
        }
        
        return $normalized;
    }
}
```

### Prompt 3.2 — Meta API Client

```
Port the Meta WhatsApp Cloud API client for Rovix AI Leads Tool.

Reference: src/lib/whatsapp/meta-api.ts

Create app/Libraries/WhatsApp/MetaApi.php:

<?php
namespace App\Libraries\WhatsApp;

use CodeIgniter\HTTP\CURLRequest;

class MetaApi
{
    private const BASE_URL = 'https://graph.facebook.com/v21.0/';
    private const TIMEOUT = 30;

    /**
     * Make API call to Meta
     */
    private function callApi(string $method, string $url, ?array $data, string $accessToken): array
    {
        $client = \Config\Services::curlrequest([
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ]
        ]);

        try {
            if ($method === 'GET') {
                $response = $client->get(self::BASE_URL . $url);
            } elseif ($method === 'POST') {
                $response = $client->post(self::BASE_URL . $url, [
                    'json' => $data
                ]);
            } elseif ($method === 'DELETE') {
                $response = $client->delete(self::BASE_URL . $url);
            } else {
                throw new \Exception('Unsupported HTTP method: ' . $method);
            }

            $body = $response->getBody();
            $result = json_decode($body, true);

            if ($response->getStatusCode() >= 400) {
                log_message('error', 'Meta API error: ' . $body);
                throw new \Exception('Meta API error: ' . ($result['error']['message'] ?? 'Unknown error'));
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Meta API call failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send text message
     */
    public function sendText(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $text,
        ?string $replyToMessageId = null
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text]
        ];

        if ($replyToMessageId) {
            $data['context'] = ['message_id' => $replyToMessageId];
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send image
     */
    public function sendImage(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $imageUrl,
        ?string $caption = null
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => ['link' => $imageUrl]
        ];

        if ($caption) {
            $data['image']['caption'] = $caption;
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send video
     */
    public function sendVideo(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $videoUrl,
        ?string $caption = null
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'video',
            'video' => ['link' => $videoUrl]
        ];

        if ($caption) {
            $data['video']['caption'] = $caption;
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send document
     */
    public function sendDocument(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $documentUrl,
        string $filename,
        ?string $caption = null
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename
            ]
        ];

        if ($caption) {
            $data['document']['caption'] = $caption;
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send audio
     */
    public function sendAudio(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $audioUrl
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'audio',
            'audio' => ['link' => $audioUrl]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send template message
     */
    public function sendTemplate(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $templateName,
        string $language,
        array $components = []
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components
            ]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send reaction
     */
    public function sendReaction(
        string $phoneNumberId,
        string $accessToken,
        string $messageId,
        string $emoji
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $messageId,
                'emoji' => $emoji
            ]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send interactive buttons
     */
    public function sendInteractiveButtons(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $bodyText,
        array $buttons,
        ?string $headerText = null
    ): array {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => ['buttons' => $buttons]
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Send interactive list
     */
    public function sendInteractiveList(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $bodyText,
        string $buttonText,
        array $sections
    ): array {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => $buttonText,
                    'sections' => $sections
                ]
            ]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    /**
     * Get media URL from media ID
     */
    public function getMediaUrl(string $mediaId, string $accessToken): string
    {
        $result = $this->callApi('GET', $mediaId, null, $accessToken);
        return $result['url'] ?? throw new \Exception('Media URL not found');
    }

    /**
     * Download media from URL
     */
    public function downloadMedia(string $url, string $accessToken): string
    {
        $client = \Config\Services::curlrequest([
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ]
        ]);

        $response = $client->get($url);
        
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to download media');
        }

        // Generate filename
        $filename = uniqid('media_') . '.bin';
        $uploadPath = WRITEPATH . 'uploads/chat-media/' . date('Y/m') . '/';
        
        // Create directory if not exists
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $filePath = $uploadPath . $filename;
        file_put_contents($filePath, $response->getBody());

        return str_replace(WRITEPATH . 'uploads/', '', $filePath);
    }

    /**
     * Upload media file to Meta
     */
    public function uploadMedia(
        string $phoneNumberId,
        string $accessToken,
        string $filePath,
        string $mimeType
    ): string {
        // Use multipart form data for file upload
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::BASE_URL . "{$phoneNumberId}/media");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'messaging_product' => 'whatsapp',
            'file' => new \CURLFile($filePath, $mimeType),
            'type' => $mimeType
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to upload media: ' . $response);
        }

        $result = json_decode($response, true);
        return $result['id'] ?? throw new \Exception('Media ID not found in response');
    }
}

Create app/Libraries/WhatsApp/TemplateSendBuilder.php:

<?php
namespace App\Libraries\WhatsApp;

class TemplateSendBuilder
{
    /**
     * Build template components array from template model + variables
     * 
     * @param array $template Template record from message_templates table
     * @param array $variables Key-value pairs for variable substitution
     * @return array Components array for Meta API
     */
    public static function buildComponents(array $template, array $variables): array
    {
        $components = [];

        // Header component
        if ($template['header_type'] !== 'none' && !empty($template['header_content'])) {
            $header = [
                'type' => 'header',
                'parameters' => []
            ];

            if ($template['header_type'] === 'text') {
                $header['parameters'][] = [
                    'type' => 'text',
                    'text' => $variables['header'] ?? $template['header_content']
                ];
            } elseif (in_array($template['header_type'], ['image', 'video', 'document'])) {
                $header['parameters'][] = [
                    'type' => $template['header_type'],
                    $template['header_type'] => [
                        'link' => $variables['header_url'] ?? $template['header_content']
                    ]
                ];
            }

            $components[] = $header;
        }

        // Body component (always present)
        $bodyParams = [];
        preg_match_all('/\{\{(\d+)\}\}/', $template['body_text'], $matches);
        
        foreach ($matches[1] as $index) {
            $key = 'body_' . $index;
            $bodyParams[] = [
                'type' => 'text',
                'text' => $variables[$key] ?? ''
            ];
        }

        if (!empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams
            ];
        }

        // Button component (if has buttons)
        if (!empty($template['buttons'])) {
            $buttons = json_decode($template['buttons'], true);
            // Note: Button variables not commonly used, skip for MVP
        }

        return $components;
    }
}
```

This is Part 1 of Phase 3. Continue to next file?
