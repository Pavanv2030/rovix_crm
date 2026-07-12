<?php

namespace App\Libraries\WhatsApp;

class MetaApi
{
    public const GRAPH_API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com/' . self::GRAPH_API_VERSION . '/';
    private const TIMEOUT  = 30;

    private function callApi(string $method, string $url, ?array $data, string $accessToken): array
    {
        $client = \Config\Services::curlrequest([
            'timeout'     => self::TIMEOUT,
            'http_errors' => false,
            'headers'     => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
        ]);

        try {
            $response = match($method) {
                'GET'    => $client->get(self::BASE_URL . $url),
                'POST'   => $client->post(self::BASE_URL . $url, ['json' => $data]),
                'DELETE' => $client->delete(self::BASE_URL . $url),
                default  => throw new \Exception('Unsupported HTTP method: ' . $method),
            };

            $body   = $response->getBody();
            $result = json_decode($body, true) ?? [];

            if ($response->getStatusCode() >= 400 || isset($result['error'])) {
                log_message('error', 'Meta API error: ' . $body);
                $errMsg  = $result['error']['message'] ?? ('HTTP ' . $response->getStatusCode());
                $errCode = $result['error']['code']    ?? $response->getStatusCode();
                throw new \Exception("Meta API error [{$errCode}]: {$errMsg}");
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Meta API call failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function sendText(string $phoneNumberId, string $accessToken, string $to, string $text, ?string $replyToMessageId = null): array
    {
        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $text],
        ];

        if ($replyToMessageId) {
            $data['context'] = ['message_id' => $replyToMessageId];
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    public function sendImage(string $phoneNumberId, string $accessToken, string $to, string $imageUrl, ?string $caption = null): array
    {
        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'image',
            'image'             => ['link' => $imageUrl],
        ];

        if ($caption) $data['image']['caption'] = $caption;

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    public function sendVideo(string $phoneNumberId, string $accessToken, string $to, string $videoUrl, ?string $caption = null): array
    {
        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'video',
            'video'             => ['link' => $videoUrl],
        ];

        if ($caption) $data['video']['caption'] = $caption;

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    public function sendDocument(string $phoneNumberId, string $accessToken, string $to, string $documentUrl, string $filename, ?string $caption = null): array
    {
        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'document',
            'document'          => ['link' => $documentUrl, 'filename' => $filename],
        ];

        if ($caption) $data['document']['caption'] = $caption;

        return $this->callApi('POST', "{$phoneNumberId}/messages", $data, $accessToken);
    }

    public function sendAudio(string $phoneNumberId, string $accessToken, string $to, string $audioUrl): array
    {
        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'audio',
            'audio'             => ['link' => $audioUrl],
        ], $accessToken);
    }

    public function sendTemplate(string $phoneNumberId, string $accessToken, string $to, string $templateName, string $language, array $components = []): array
    {
        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $language],
                'components' => $components,
            ],
        ], $accessToken);
    }

    public function sendReaction(string $phoneNumberId, string $accessToken, string $to, string $messageId, string $emoji): array
    {
        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'reaction',
            'reaction'          => ['message_id' => $messageId, 'emoji' => $emoji],
        ], $accessToken);
    }

    public function sendInteractiveButtons(string $phoneNumberId, string $accessToken, string $to, string $bodyText, array $buttons, ?string $headerText = null): array
    {
        $interactive = [
            'type'   => 'button',
            'body'   => ['text' => $bodyText],
            'action' => ['buttons' => $buttons],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ], $accessToken);
    }

    public function sendInteractiveList(string $phoneNumberId, string $accessToken, string $to, string $bodyText, string $buttonText, array $sections): array
    {
        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'list',
                'body'   => ['text' => $bodyText],
                'action' => ['button' => $buttonText, 'sections' => $sections],
            ],
        ], $accessToken);
    }

    public function sendMediaButtons(string $phoneNumberId, string $accessToken, string $to, string $mediaType, string $mediaUrl, string $bodyText, array $buttons, ?string $caption = null): array
    {
        $header = $mediaType === 'image'
            ? ['type' => 'image', 'image' => ['link' => $mediaUrl]]
            : ['type' => 'video', 'video' => ['link' => $mediaUrl]];

        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'button',
                'header' => $header,
                'body'   => ['text' => $caption ?: $bodyText],
                'action' => ['buttons' => array_map(fn($b) => ['type' => 'reply', 'reply' => ['id' => $b['id'], 'title' => $b['title']]], array_slice($buttons, 0, 3))],
            ],
        ], $accessToken);
    }

    public function sendCtaUrlButton(string $phoneNumberId, string $accessToken, string $to, string $bodyText, string $buttonText, string $url, ?string $footerText = null): array
    {
        $interactive = [
            'type'   => 'cta_url',
            'body'   => ['text' => $bodyText],
            'action' => [
                'name'       => 'cta_url',
                // Meta caps display_text at 20 characters — longer values fail with 131009.
                'parameters' => ['display_text' => mb_substr($buttonText, 0, 20), 'url' => $url],
            ],
        ];

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ], $accessToken);
    }

    public function sendLocationRequest(string $phoneNumberId, string $accessToken, string $to, string $bodyText): array
    {
        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'location_request_message',
                'body'   => ['text' => $bodyText],
                'action' => ['name' => 'send_location'],
            ],
        ], $accessToken);
    }

    public function getMediaUrl(string $mediaId, string $accessToken): string
    {
        $result = $this->callApi('GET', $mediaId, null, $accessToken);
        return $result['url'] ?? throw new \Exception('Media URL not found');
    }

    public function downloadMedia(string $url, string $accessToken): array
    {
        $client = \Config\Services::curlrequest([
            'timeout' => 60,
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);

        $response = $client->get($url);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to download media');
        }

        $mimeType  = explode(';', $response->getHeaderLine('Content-Type') ?: 'application/octet-stream')[0];
        $mimeType  = trim($mimeType) ?: 'application/octet-stream';
        $extension = self::mimeToExtension($mimeType);

        $uploadPath = WRITEPATH . 'uploads/chat-media/' . date('Y/m') . '/';

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $filename = uniqid('media_') . '.' . $extension;
        $filePath = $uploadPath . $filename;
        file_put_contents($filePath, $response->getBody());

        return [str_replace(WRITEPATH . 'uploads/', '', $filePath), $mimeType];
    }

    private static function mimeToExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg'        => 'jpg',
            'image/png'         => 'png',
            'image/gif'         => 'gif',
            'image/webp'        => 'webp',
            'video/mp4'         => 'mp4',
            'video/quicktime'   => 'mov',
            'audio/mpeg'        => 'mp3',
            'audio/ogg'         => 'ogg',
            'audio/aac'         => 'aac',
            'audio/wav'         => 'wav',
            'application/pdf'   => 'pdf',
            default             => 'bin',
        };
    }

    public function uploadMedia(string $phoneNumberId, string $accessToken, string $filePath, string $mimeType): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE_URL . "{$phoneNumberId}/media",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_POSTFIELDS     => [
                'messaging_product' => 'whatsapp',
                'file'              => new \CURLFile($filePath, $mimeType),
                'type'              => $mimeType,
            ],
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

    public function getCatalogs(string $phoneNumberId, string $accessToken): array
    {
        // Returns commerce settings incl. catalog_id linked to this phone number
        $result   = $this->callApi('GET', "{$phoneNumberId}/whatsapp_commerce_settings", null, $accessToken);
        $settings = $result['data'] ?? [];

        // Map to uniform {id, name} shape expected by the UI
        $catalogs = [];
        foreach ($settings as $s) {
            if (!empty($s['catalog_id'])) {
                $catalogs[] = [
                    'id'   => $s['catalog_id'],
                    'name' => 'Catalog ' . $s['catalog_id'],
                ];
            }
        }

        return ['data' => $catalogs];
    }

    public function getCatalogProducts(string $catalogId, string $accessToken): array
    {
        $result = $this->callApi(
            'GET',
            "{$catalogId}/products?fields=id,name,description,price,sale_price,image_url,availability,retailer_id&limit=100",
            null,
            $accessToken
        );

        // Meta Commerce Manager shows "Content ID" but API may return empty retailer_id.
        // Use id as fallback so product sends don't fail with 131009.
        foreach ($result['data'] ?? [] as &$product) {
            if (empty($product['retailer_id'])) {
                log_message('info', "Product {$product['id']} missing retailer_id, using id as fallback");
                $product['retailer_id'] = $product['id'];
            }
        }

        return $result;
    }

    /**
     * A catalog can be fully attached to the WABA (via WhatsApp Manager,
     * visible in product_catalogs) while the phone number's own
     * whatsapp_commerce_settings object still doesn't exist — the WhatsApp
     * Manager UI toggles do not reliably create it. Without this object,
     * catalog_message sends fail with error 131009 "Products not found in
     * FB Catalog" even though the catalog and its products are valid.
     * Call this right after connecting a catalog to guarantee it's set.
     */
    public function enableCommerceSettings(string $phoneNumberId, string $accessToken): array
    {
        $client = \Config\Services::curlrequest(['timeout' => 20, 'http_errors' => false]);
        $response = $client->post(self::BASE_URL . "{$phoneNumberId}/whatsapp_commerce_settings", [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'query'   => [
                'is_catalog_visible' => 'true',
                'is_cart_enabled'    => 'true',
            ],
        ]);

        $result = json_decode($response->getBody(), true) ?? [];
        if ($response->getStatusCode() >= 400) {
            $msg = $result['error']['message'] ?? ('HTTP ' . $response->getStatusCode());
            $details = json_encode($result);
            log_message('error', "enableCommerceSettings API response: {$details}");
            throw new \Exception("Failed to enable commerce settings: {$msg}");
        }

        return $result;
    }

    public function getPhoneNumberInfo(string $phoneNumberId, string $accessToken): array
    {
        // account_mode is NOT a valid field on the phone-number node (causes Meta HTTP 400).
        $fields = implode(',', [
            'display_phone_number',
            'verified_name',
            'quality_rating',
            'name_status',
            'platform_type',
            'code_verification_status',
            'status',
        ]);

        return $this->callApi('GET', "{$phoneNumberId}?fields={$fields}", null, $accessToken);
    }

    /**
     * Registers the business's RSA public key on the phone number. Required
     * before Meta will allow publishing (or health-checking) any Flow that
     * has a Data Exchange endpoint.
     */
    public function setFlowPublicKey(string $phoneNumberId, string $accessToken, string $publicKeyPem): array
    {
        $client   = \Config\Services::curlrequest(['timeout' => 20, 'http_errors' => false]);
        $response = $client->post(self::BASE_URL . "{$phoneNumberId}/whatsapp_business_encryption", [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'query'   => ['business_public_key' => $publicKeyPem],
        ]);

        $result = json_decode($response->getBody(), true) ?? [];
        if ($response->getStatusCode() >= 400) {
            $msg = $result['error']['message'] ?? ('HTTP ' . $response->getStatusCode());
            throw new \Exception("Failed to set flow public key: {$msg}");
        }

        return $result;
    }

    public function createFlow(string $wabaId, string $accessToken, string $name, array $categories = ['APPOINTMENT_BOOKING']): array
    {
        return $this->callApi('POST', "{$wabaId}/flows", [
            'name'       => $name,
            'categories' => $categories,
        ], $accessToken);
    }

    public function uploadFlowAsset(string $flowId, string $accessToken, array $flowJson): array
    {
        $jsonContent = json_encode($flowJson);
        $boundary    = '----FormBoundary' . uniqid();

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"name\"\r\n\r\nflow.json\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"asset_type\"\r\n\r\nFLOW_JSON\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"flow.json\"\r\n";
        $body .= "Content-Type: application/json\r\n\r\n";
        $body .= $jsonContent . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $client   = \Config\Services::curlrequest(['timeout' => 30, 'http_errors' => false]);
        $response = $client->post(self::BASE_URL . "{$flowId}/assets", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => "multipart/form-data; boundary={$boundary}",
            ],
            'body' => $body,
        ]);

        $result = json_decode($response->getBody(), true) ?? [];
        if ($response->getStatusCode() >= 400 || isset($result['error'])) {
            $msg = $result['error']['message'] ?? ('HTTP ' . $response->getStatusCode());
            throw new \Exception("Meta Flow asset upload error: {$msg}");
        }
        return $result;
    }

    public function publishFlow(string $flowId, string $accessToken): array
    {
        return $this->callApi('POST', "{$flowId}/publish", [], $accessToken);
    }

    /**
     * Sets the Flow's Data Exchange endpoint via API. Meta rejects publish
     * with "endpoint_uri is forbidden... you need to set it" for any flow
     * created programmatically — it does not inherit a value from manually
     * pasting the URL into the dashboard for a *different* flow, and a
     * freshly auto-created flow has no endpoint_uri until this is called.
     */
    public function setFlowEndpoint(string $flowId, string $accessToken, string $endpointUri): array
    {
        return $this->callApi('POST', "{$flowId}", ['endpoint_uri' => $endpointUri], $accessToken);
    }

    /**
     * Uploads a remote file into Meta's Resumable Upload API and returns a
     * file handle ("h") usable as a template header_handle example. Message
     * templates with IMAGE/VIDEO/DOCUMENT headers require this — a plain
     * external URL is rejected by Meta (error 100 "Missing sample parameter").
     */
    public function uploadTemplateMedia(string $appId, string $accessToken, string $fileUrl): string
    {
        // file_get_contents() has no timeout/retry and fails silently on
        // transient DNS/network hiccups. Use a real HTTP client with a
        // couple of retries instead.
        $fileBytes = null;
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $dlClient  = \Config\Services::curlrequest(['timeout' => 20, 'http_errors' => false]);
            $dlResponse = $dlClient->get($fileUrl);
            if ($dlResponse->getStatusCode() < 400) {
                $fileBytes = $dlResponse->getBody();
                break;
            }
            $lastError = 'HTTP ' . $dlResponse->getStatusCode();
            if ($attempt < 3) usleep(300_000);
        }

        if ($fileBytes === null || $fileBytes === '') {
            throw new \Exception("Could not download header media from: {$fileUrl} ({$lastError})");
        }

        $fileLength = strlen($fileBytes);
        $mimeType   = $this->guessMimeType($fileUrl);

        // Step 1: start an upload session
        $client   = \Config\Services::curlrequest(['timeout' => 30, 'http_errors' => false]);
        $response = $client->post(self::BASE_URL . "{$appId}/uploads", [
            'query'   => [
                'file_length' => $fileLength,
                'file_type'   => $mimeType,
                'access_token' => $accessToken,
            ],
        ]);

        $session = json_decode($response->getBody(), true) ?? [];
        if ($response->getStatusCode() >= 400 || empty($session['id'])) {
            $msg = $session['error']['message'] ?? ('HTTP ' . $response->getStatusCode());
            throw new \Exception("Meta upload session error: {$msg}");
        }

        // Step 2: push the file bytes to the session
        $uploadResponse = $client->post(self::BASE_URL . $session['id'], [
            'headers' => [
                'Authorization' => 'OAuth ' . $accessToken,
                'file_offset'   => '0',
                'Content-Type'  => 'application/octet-stream',
            ],
            'body' => $fileBytes,
        ]);

        $result = json_decode($uploadResponse->getBody(), true) ?? [];
        if ($uploadResponse->getStatusCode() >= 400 || empty($result['h'])) {
            $msg = $result['error']['message'] ?? ('HTTP ' . $uploadResponse->getStatusCode());
            throw new \Exception("Meta file upload error: {$msg}");
        }

        return $result['h'];
    }

    private function guessMimeType(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return match ($ext) {
            'png'          => 'image/png',
            'jpg', 'jpeg'  => 'image/jpeg',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'mp4'          => 'video/mp4',
            'pdf'          => 'application/pdf',
            default        => 'application/octet-stream',
        };
    }

    public function sendFlowMessage(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $bodyText,
        string $buttonText,
        string $flowId,
        string $flowToken,
        array  $flowData = []
    ): array {
        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type' => 'flow',
                'body' => ['text' => $bodyText],
                'action' => [
                    'name'       => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_token'           => $flowToken,
                        'flow_id'              => $flowId,
                        'flow_cta'             => $buttonText,
                        'flow_action'          => 'navigate',
                        'flow_action_payload'  => [
                            'screen' => 'SELECT_DATE',
                            'data'   => array_merge(['flow_token' => $flowToken], $flowData),
                        ],
                    ],
                ],
            ],
        ], $accessToken);
    }

    public function sendCatalogMessage(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $bodyText,
        ?string $footerText = null,
        ?string $thumbnailRetailerId = null
    ): array {
        // Meta restricts the full "catalog_message" interactive type — it
        // cannot be delivered to WhatsApp users in India (+91) under local
        // commerce regulations. Fail fast with a clear reason instead of
        // letting Meta reject it with an opaque error code.
        if (str_starts_with($to, '91') && strlen($to) === 12) {
            throw new \Exception('Catalog messages cannot be sent to Indian (+91) WhatsApp numbers per Meta\'s regional restrictions. Use Send Product/Product List instead.');
        }

        $action = ['name' => 'catalog_message'];
        // thumbnail_product_retailer_id is OPTIONAL. Sending an empty/invalid
        // value triggers Meta error 131009 — only include it when present.
        if (!empty($thumbnailRetailerId)) {
            $action['parameters'] = [
                'thumbnail_product_retailer_id' => $thumbnailRetailerId,
            ];
        }

        $interactive = [
            'type'   => 'catalog_message',
            'body'   => ['text' => $bodyText ?: 'Browse our products'],
            'action' => $action,
        ];
        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ], $accessToken);
    }

    public function sendSingleProduct(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $catalogId,
        string $productRetailerId,
        ?string $bodyText = null
    ): array {
        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'product',
                'body'   => ['text' => $bodyText ?: 'Check out this product'],
                'action' => [
                    'catalog_id'          => $catalogId,
                    'product_retailer_id' => $productRetailerId,
                ],
            ],
        ], $accessToken);
    }

    public function sendMultiProduct(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $catalogId,
        string $headerText,
        string $bodyText,
        array  $sections,
        ?string $footerText = null
    ): array {
        if (count($sections) > 10) {
            throw new \Exception('Product list exceeds Meta\'s limit of 10 sections per message');
        }
        $totalProducts = array_sum(array_map(fn($s) => count($s['product_items'] ?? []), $sections));
        if ($totalProducts > 30) {
            throw new \Exception('Product list exceeds Meta\'s limit of 30 products per message');
        }

        $interactive = [
            'type'   => 'product_list',
            'header' => ['type' => 'text', 'text' => $headerText],
            'body'   => ['text' => $bodyText],
            'action' => [
                'catalog_id' => $catalogId,
                'sections'   => $sections,
            ],
        ];
        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ], $accessToken);
    }

    /**
     * Resolve the WhatsApp Business Account ID for the connected phone number.
     * Uses debug_token granular scopes, then verifies via WABA phone_numbers edge.
     */
    public function resolveWabaId(string $phoneNumberId, string $accessToken, ?string $hintWabaId = null): string
    {
        $hintWabaId = trim((string) $hintWabaId);
        if ($hintWabaId !== '' && $hintWabaId !== $phoneNumberId
            && $this->wabaOwnsPhoneNumber($hintWabaId, $phoneNumberId, $accessToken)) {
            return $hintWabaId;
        }

        $candidates = $this->wabaIdsFromAccessToken($accessToken);
        foreach ($candidates as $candidate) {
            if ($this->wabaOwnsPhoneNumber($candidate, $phoneNumberId, $accessToken)) {
                return $candidate;
            }
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        throw new \Exception(
            'Could not resolve WhatsApp Business Account ID. '
            . 'Open Meta → WhatsApp → API Setup and paste the WhatsApp Business Account ID '
            . '(not the Phone Number ID) into Settings → WhatsApp.'
        );
    }

    /**
     * @return list<string>
     */
    public function wabaIdsFromAccessToken(string $accessToken): array
    {
        $appId     = env('META_APP_ID');
        $appSecret = config('WhatsApp')->metaAppSecret ?? env('whatsapp.metaAppSecret', '');
        if (!$appId || !$appSecret) {
            return [];
        }

        $client = \Config\Services::curlrequest(['timeout' => 15, 'http_errors' => false]);
        $url    = self::BASE_URL . 'debug_token'
            . '?input_token=' . rawurlencode($accessToken)
            . '&access_token=' . rawurlencode($appId . '|' . $appSecret);

        $response = $client->get($url);
        $result   = json_decode($response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 400 || isset($result['error'])) {
            log_message('warning', 'debug_token failed: ' . ($response->getBody() ?? ''));
            return [];
        }

        $ids = [];
        foreach ($result['data']['granular_scopes'] ?? [] as $scope) {
            $name = $scope['scope'] ?? '';
            if (!in_array($name, ['whatsapp_business_management', 'whatsapp_business_messaging', 'business_management'], true)) {
                continue;
            }
            foreach ($scope['target_ids'] ?? [] as $id) {
                $ids[] = (string) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function wabaOwnsPhoneNumber(string $wabaId, string $phoneNumberId, string $accessToken): bool
    {
        try {
            $result = $this->callApi('GET', "{$wabaId}/phone_numbers?fields=id&limit=50", null, $accessToken);
            foreach ($result['data'] ?? [] as $row) {
                if ((string) ($row['id'] ?? '') === (string) $phoneNumberId) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            log_message('warning', "wabaOwnsPhoneNumber check failed for {$wabaId}: " . $e->getMessage());
        }

        return false;
    }

    /**
     * List message templates on a WhatsApp Business Account.
     *
     * @see https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/message_templates/
     */
    public function listMessageTemplates(string $wabaId, string $accessToken, ?string $after = null, int $limit = 100): array
    {
        $url = "{$wabaId}/message_templates?limit={$limit}";
        if ($after) {
            $url .= '&after=' . rawurlencode($after);
        }

        return $this->callApi('GET', $url, null, $accessToken);
    }
}
