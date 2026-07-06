<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\JobDispatcher;
use App\Libraries\LeadStatusApplier;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\PhoneUtils;
use App\Models\BaseModel;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\ConversationStatusModel;
use App\Models\MessageModel;
use App\Models\MessageReactionModel;
use App\Models\MessageTemplateModel;
use App\Models\WhatsAppConfigModel;
use App\Models\AccountModel;

class WebhookController extends BaseController
{
    public function verify()
    {
        // PHP converts dots to underscores in $_GET, so parse raw query string
        $params = [];
        foreach (explode('&', $_SERVER['QUERY_STRING'] ?? '') as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $params[urldecode($k)] = urldecode($v);
        }

        $hubMode      = $params['hub.mode'] ?? '';
        $hubToken     = $params['hub.verify_token'] ?? '';
        $hubChallenge = $params['hub.challenge'] ?? '';

        $verifyToken = config('WhatsApp')->verifyToken;

        if ($hubMode === 'subscribe' && $hubToken === $verifyToken) {
            log_message('info', 'Webhook verified successfully');
            return $this->response->setContentType('text/plain')->setBody($hubChallenge);
        }

        log_message('error', 'Webhook verification failed');
        return $this->response->setStatusCode(403)->setJSON(['error' => 'Verification failed']);
    }

    public function handle()
    {
        $startTime    = microtime(true);
        $success      = true;
        $errorMessage = null;
        $accountId    = null;
        $eventType    = 'unknown';

        try {
            BaseModel::setBypassAccountScope(true);

            $body = $this->request->getJSON(true);

            if (empty($body['entry'])) {
                return $this->response->setStatusCode(200)->setJSON(['status' => 'ok']);
            }

            // Resolve account and event type from first entry for logging
            $firstChange = $body['entry'][0]['changes'][0] ?? [];
            $eventType   = $firstChange['field'] ?? 'unknown';
            $phoneId     = $firstChange['value']['metadata']['phone_number_id'] ?? null;
            if ($phoneId) {
                $cfg = (new WhatsAppConfigModel())->where('phone_number_id', $phoneId)->first();
                $accountId = $cfg['account_id'] ?? null;
            }

            foreach ($body['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $value = $change['value'];

                    if (isset($value['messages'])) {
                        foreach ($value['messages'] as $message) {
                            try {
                                $this->processInboundMessage(
                                    $value['metadata']['phone_number_id'],
                                    $message,
                                    $value['contacts'][0] ?? null
                                );
                            } catch (\Exception $e) {
                                log_message('error', 'Error processing message: ' . $e->getMessage());
                            }
                        }
                    }

                    if (isset($value['statuses'])) {
                        foreach ($value['statuses'] as $status) {
                            try {
                                $this->processStatusUpdate($status);
                            } catch (\Exception $e) {
                                log_message('error', 'Error processing status: ' . $e->getMessage());
                            }
                        }
                    }

                    if (isset($value['message_template_status_update'])) {
                        try {
                            $this->processTemplateStatus($value['message_template_status_update']);
                        } catch (\Exception $e) {
                            log_message('error', 'Error processing template status: ' . $e->getMessage());
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $success      = false;
            $errorMessage = $e->getMessage();
            log_message('error', 'Webhook processing failed: ' . $e->getMessage());
        }

        // Log this webhook event (only when we know which account it belongs to)
        if ($accountId) {
            $processingMs = (int) ((microtime(true) - $startTime) * 1000);
            try {
                \Config\Database::connect()->table('webhook_logs')->insert([
                    'account_id'         => $accountId,
                    'event_type'         => $eventType,
                    'payload'            => $this->request->getBody(),
                    'status'             => $success ? 'success' : 'failed',
                    'error_message'      => $errorMessage,
                    'processing_time_ms' => $processingMs,
                    'created_at'         => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to write webhook log: ' . $e->getMessage());
            }
        }

        return $this->response->setStatusCode(200)->setJSON(['status' => 'ok']);
    }

    private function processInboundMessage(string $phoneNumberId, array $message, ?array $contactInfo)
    {
        $waConfig = (new WhatsAppConfigModel())->where('phone_number_id', $phoneNumberId)->first();

        if (!$waConfig) {
            log_message('error', "No WhatsApp config found for phone_number_id: {$phoneNumberId}");
            return;
        }

        $accountId       = $waConfig['account_id'];
        $from            = $message['from'];
        $messageType     = $message['type'];
        $waMessageId     = $message['id'];
        $phoneNormalized = PhoneUtils::normalize($from);

        $contactModel = new ContactModel();
        $contact      = $contactModel->where('account_id', $accountId)->where('phone_normalized', $phoneNormalized)->first();

        if (!$contact) {
            $contactId = $contactModel->insert([
                'account_id'       => $accountId,
                'phone'            => $from,
                'phone_normalized' => $phoneNormalized,
                'name'             => $contactInfo['profile']['name'] ?? $from,
            ]);
            $contact = $contactModel->find($contactId);
        }

        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->where('account_id', $accountId)->where('contact_id', $contact['id'])->first();

        if (!$conversation) {
            $conversationId = $conversationModel->insert([
                'account_id' => $accountId,
                'contact_id' => $contact['id'],
                'status'     => 'open',
            ]);
            $conversation = $conversationModel->find($conversationId);
        }

        if ($messageType === 'reaction') {
            $this->handleReaction($accountId, $conversation['id'], $message['reaction']);
            return;
        }

        $contentText    = null;
        $mediaUrl       = null;
        $mediaMimeType  = null;
        $mediaFilename  = null;
        $isVoiceNote    = false;

        switch ($messageType) {
            case 'text':
                $contentText = $message['text']['body'];
                break;
            case 'image':
                $contentText = $message['image']['caption'] ?? null;
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($message['image']['id'], $waConfig, $accountId);
                break;
            case 'video':
                $contentText = $message['video']['caption'] ?? null;
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($message['video']['id'], $waConfig, $accountId);
                break;
            case 'document':
                $contentText   = $message['document']['caption'] ?? null;
                $mediaFilename = $message['document']['filename'] ?? 'document';
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($message['document']['id'], $waConfig, $accountId);
                break;
            case 'audio':
                $isVoiceNote = (bool) ($message['audio']['voice'] ?? false);
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($message['audio']['id'], $waConfig, $accountId);
                break;
            case 'sticker':
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($message['sticker']['id'], $waConfig, $accountId);
                break;
            case 'location':
                $loc         = $message['location'];
                $contentText = "Location: {$loc['latitude']}, {$loc['longitude']}";
                break;
            case 'interactive':
                $contentText = $this->extractInteractiveResponse($message['interactive']);
                break;
            case 'button':
                // Customer tapped a Quick Reply button on a template message.
                // Meta sends this as a top-level "button" type, distinct from
                // interactive.button_reply — was falling through to the
                // generic "Unsupported message type" default.
                $contentText = $message['button']['text'] ?? 'Button reply';
                break;
            case 'contacts':
                $names = array_map(
                    fn($c) => $c['name']['formatted_name'] ?? 'Contact',
                    $message['contacts'] ?? []
                );
                $contentText = 'Shared contact: ' . implode(', ', $names);
                break;
            case 'order':
                $contentText = $this->processOrderMessage(
                    $accountId,
                    $contact['id'],
                    $conversation['id'],
                    $message['order'] ?? []
                );
                break;
            case 'unsupported':
                // Meta sends this for message kinds it doesn't relay content
                // for over the Cloud API — most commonly a customer deleting
                // a message they sent, but also polls and a few other types
                // Meta lumps into the same bucket. There's no way to tell
                // these apart from the payload alone.
                $contentText = $message['errors'][0]['title'] ?? 'Unsupported message';
                break;
            default:
                $contentText = "Unsupported message type: {$messageType}";
        }

        (new MessageModel())->insert([
            'conversation_id'     => $conversation['id'],
            'account_id'          => $accountId,
            'sender_type'         => 'customer',
            'content_type'        => $messageType,
            'content_text'        => $contentText,
            'media_url'           => $mediaUrl,
            'media_mime_type'     => $mediaMimeType,
            'media_filename'      => $mediaFilename,
            'is_voice_note'       => $isVoiceNote ? 1 : 0,
            'status'              => 'sent',
            'whatsapp_message_id' => $waMessageId,
            'reply_to_message_id' => $message['context']['id'] ?? null,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $conversationModel->update($conversation['id'], [
            'last_message_text'        => $contentText ? substr($contentText, 0, 200) : '[' . ucfirst($messageType) . ']',
            'last_message_at'          => date('Y-m-d H:i:s'),
            'last_customer_message_at' => date('Y-m-d H:i:s'),
            'unread_count'             => $conversation['unread_count'] + 1,
            'status'                   => 'open',
        ]);

        // Handle appointment flow completion (nfm_reply)
        if ($messageType === 'interactive' && isset($message['interactive']['nfm_reply'])) {
            try {
                $this->processFlowCompletion(
                    $message['interactive']['nfm_reply'],
                    $contact, $conversation, $waConfig, $accountId
                );
            } catch (\Exception $e) {
                log_message('error', 'Flow completion processing failed: ' . $e->getMessage());
            }
        }

        // Handle BOOK keyword for re-booking
        if ($messageType === 'text' && strtoupper(trim($contentText ?? '')) === 'BOOK') {
            try {
                $this->handleRebookRequest($contact, $conversation, $waConfig);
            } catch (\Exception $e) {
                log_message('error', 'Rebook request failed: ' . $e->getMessage());
            }
        }

        // "Interested" quick-reply button on a broadcast template — tags the
        // conversation as Hot Lead automatically instead of an agent having
        // to notice and set it manually. Covers both a template's top-level
        // "button" tap and a free-form interactive button reply.
        if (in_array($messageType, ['button', 'interactive'], true) && strtolower(trim($contentText ?? '')) === 'interested') {
            try {
                $this->markHotLead($conversation['id'], $accountId);
            } catch (\Exception $e) {
                log_message('error', 'Hot lead auto-tag failed: ' . $e->getMessage());
            }
        }

        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('run_automation', [
            'account_id'      => $accountId,
            'contact_id'      => $contact['id'],
            'conversation_id' => $conversation['id'],
            'message'         => $message,
            'message_text'    => $contentText,
            'trigger_type'    => 'new_message_received',
        ], null, 5);

        $dispatcher->dispatch('check_flow', [
            'account_id'      => $accountId,
            'contact_id'      => $contact['id'],
            'conversation_id' => $conversation['id'],
            'message_text'    => $contentText,
        ], null, 5);
    }

    private function handleReaction(string $accountId, string $conversationId, array $reaction)
    {
        $message = (new MessageModel())->where('whatsapp_message_id', $reaction['message_id'])->first();

        if (!$message) {
            log_message('warning', 'Message not found for reaction: ' . $reaction['message_id']);
            return;
        }

        $reactionModel = new MessageReactionModel();

        if (empty($reaction['emoji'])) {
            $reactionModel->where('message_id', $message['id'])->where('actor_type', 'customer')->delete();
        } else {
            $reactionModel->insert([
                'message_id'      => $message['id'],
                'conversation_id' => $conversationId,
                'actor_type'      => 'customer',
                'emoji'           => $reaction['emoji'],
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function downloadMediaFromMeta(string $mediaId, array $waConfig, string $accountId): array
    {
        $encryption  = new \App\Libraries\WhatsApp\Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        $metaApi              = new MetaApi();
        $mediaUrl             = $metaApi->getMediaUrl($mediaId, $accessToken);
        [$localPath, $mime]   = $metaApi->downloadMedia($mediaUrl, $accessToken);

        (new \App\Models\MediaFileModel())->insert([
            'account_id'        => $accountId,
            'file_path'         => $localPath,
            'mime_type'         => $mime,
            'file_size'         => filesize(WRITEPATH . 'uploads/' . $localPath) ?: 0,
            'media_type'        => $this->guessMediaType($localPath),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return [$localPath, $mime, basename($localPath)];
    }

    private function guessMediaType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'image';
        if (in_array($ext, ['mp4', 'mov', 'avi']))                  return 'video';
        if (in_array($ext, ['mp3', 'ogg', 'wav', 'aac']))           return 'audio';
        return 'document';
    }

    private function processOrderMessage(
        string $accountId,
        string $contactId,
        string $conversationId,
        array $order
    ): string {
        $items     = $order['product_items'] ?? [];
        $catalogId = $order['catalog_id'] ?? null;
        $text      = $order['text'] ?? null;

        $total     = 0.0;
        $currency  = 'USD';
        $summaries = [];

        foreach ($items as $item) {
            $price      = ($item['item_price'] ?? 0) / 1000;
            $qty        = $item['quantity'] ?? 1;
            $currency   = $item['currency'] ?? 'USD';
            $retailerId = $item['product_retailer_id'] ?? '';
            $total     += $price * $qty;
            $summaries[] = "{$qty}x {$retailerId} @ {$price} {$currency}";
        }

        (new \App\Models\CatalogOrderModel())->insert([
            'id'              => generate_uuid(),
            'account_id'      => $accountId,
            'contact_id'      => $contactId,
            'conversation_id' => $conversationId,
            'catalog_id'      => $catalogId,
            'order_items'     => json_encode($items),
            'total_price'     => round($total, 2),
            'currency'        => $currency,
            'customer_note'   => $text,
            'status'          => 'new',
            'wa_order_id'     => 'wa_' . uniqid(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $summary = 'Order: ' . implode(', ', $summaries);
        if ($text) $summary .= " | Note: {$text}";
        return $summary;
    }

    private function processFlowCompletion(
        array  $nfmReply,
        array  $contact,
        array  $conversation,
        array  $waConfig,
        string $accountId
    ): void {
        $responseJson = json_decode($nfmReply['response_json'] ?? '{}', true) ?? [];

        $selectedDate = $responseJson['selected_date'] ?? null;
        $selectedTime = $responseJson['selected_time'] ?? null;
        $flowToken    = $responseJson['flow_token']    ?? ($nfmReply['flow_token'] ?? null);

        if (!$selectedDate || !$selectedTime || $selectedTime === 'none') {
            log_message('warning', 'Flow completion missing date/time: ' . json_encode($responseJson));
            return;
        }

        $db       = \Config\Database::connect();
        $flowMeta = $db->table('flow_token_map')->where('flow_token', $flowToken)->get()->getRowArray();
        $typeId   = $flowMeta['appointment_type_id'] ?? null;

        if (!$typeId) {
            log_message('warning', "No flow_token_map entry for token: {$flowToken}");
            return;
        }

        $isReschedule       = !empty($flowMeta['appointment_id']);
        $existingAppointment = $isReschedule ? (new \App\Models\AppointmentModel())->find($flowMeta['appointment_id']) : null;

        $type        = (new \App\Models\AppointmentTypeModel())->find($typeId);
        $scheduledAt = date('Y-m-d H:i:s', strtotime("{$selectedDate} {$selectedTime}"));
        $endAt       = date('Y-m-d H:i:s', strtotime($scheduledAt) + ((int)($type['duration_minutes'] ?? 30) * 60));
        $accessToken = (new \App\Libraries\WhatsApp\Encryption())->decrypt($waConfig['access_token']);

        // Google Calendar event — move the existing event on reschedule
        // (same booking, same Meet link) instead of creating a duplicate.
        $meetLink      = $existingAppointment['meet_link']       ?? null;
        $googleEventId = $existingAppointment['google_event_id'] ?? null;
        $tokenRow      = $db->table('google_oauth_tokens')->where('account_id', $accountId)->get()->getRowArray();

        if ($tokenRow && $isReschedule && $googleEventId) {
            try {
                $gc    = new \App\Libraries\GoogleCalendar();
                $token = $gc->getValidToken($tokenRow);
                $tz    = env('APP_TIMEZONE', 'Asia/Kolkata');
                $gc->updateEventTime(
                    $token,
                    $tokenRow['calendar_id'],
                    $googleEventId,
                    date('Y-m-d\TH:i:s', strtotime($scheduledAt)),
                    date('Y-m-d\TH:i:s', strtotime($endAt)),
                    $tz
                );
            } catch (\Exception $e) {
                log_message('error', 'Google Calendar event update failed: ' . $e->getMessage());
            }
        } elseif ($tokenRow && !$isReschedule) {
            try {
                $gc    = new \App\Libraries\GoogleCalendar();
                $token = $gc->getValidToken($tokenRow);
                $tz    = env('APP_TIMEZONE', 'Asia/Kolkata');
                $event = $gc->createEvent(
                    $token,
                    $tokenRow['calendar_id'],
                    "Appointment: {$type['name']} with {$contact['name']}",
                    "Customer: {$contact['name']}\nPhone: {$contact['phone']}",
                    date('Y-m-d\TH:i:s', strtotime($scheduledAt)),
                    date('Y-m-d\TH:i:s', strtotime($endAt)),
                    $tz,
                    $contact['email'] ?? ''
                );
                $meetLink      = $event['conferenceData']['entryPoints'][0]['uri'] ?? null;
                $googleEventId = $event['id'] ?? null;
            } catch (\Exception $e) {
                log_message('error', 'Google Calendar event creation failed: ' . $e->getMessage());
            }
        }

        $appointmentModel = new \App\Models\AppointmentModel();

        if ($isReschedule && $existingAppointment) {
            $bookingToken = $existingAppointment['booking_token'];
            $appointmentModel->update($existingAppointment['id'], [
                'scheduled_at' => $scheduledAt,
                'end_at'       => $endAt,
                'status'       => 'confirmed',
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        } else {
            $bookingToken = bin2hex(random_bytes(16));
            $appointmentModel->insert([
                'account_id'          => $accountId,
                'appointment_type_id' => $typeId,
                'contact_id'          => $contact['id'],
                'conversation_id'     => $conversation['id'],
                'contact_name'        => $contact['name'],
                'contact_phone'       => $contact['phone'],
                'scheduled_at'        => $scheduledAt,
                'end_at'              => $endAt,
                'status'              => 'confirmed',
                'google_event_id'     => $googleEventId,
                'meet_link'           => $meetLink,
                'price_paid'          => $type['price'] ?? 0,
                'booking_token'       => $bookingToken,
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
        }

        $bookingUrl    = base_url("booking/{$bookingToken}");
        $dateFormatted = date('D, d M Y', strtotime($scheduledAt));
        $timeFormatted = date('h:i A', strtotime($scheduledAt));

        $msg  = $isReschedule ? "🔄 *Appointment Rescheduled!*\n\n" : "✅ *Booking Confirmed!*\n\n";
        $msg .= "📋 *{$type['name']}*\n";
        $msg .= "📅 {$dateFormatted} at {$timeFormatted}\n";
        $msg .= "⏱ {$type['duration_minutes']} mins\n";
        if ($meetLink) $msg .= "🎥 Google Meet: {$meetLink}\n";
        $msg .= "\n📄 View your booking: {$bookingUrl}";

        $sendResult = (new \App\Libraries\WhatsApp\MetaApi())->sendText(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            $msg
        );

        // This confirmation is sent directly via the API, bypassing every
        // other outbound path in the app (which all insert into `messages`)
        // — without this it's invisible in the inbox even though the
        // customer actually receives it on WhatsApp.
        (new MessageModel())->insert([
            'conversation_id'     => $conversation['id'],
            'account_id'          => $accountId,
            'sender_type'         => 'bot',
            'content_type'        => 'text',
            'content_text'        => $msg,
            'status'              => 'sent',
            'whatsapp_message_id' => $sendResult['messages'][0]['id'] ?? null,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        (new ConversationModel())->update($conversation['id'], [
            'last_message_text' => $isReschedule ? 'Appointment Rescheduled!' : 'Booking Confirmed!',
            'last_message_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->notifyOwnerOfBooking($accountId, $waConfig, $accessToken, $type, $contact, $scheduledAt, $meetLink, $isReschedule);

        // Cleanup flow_token_map
        $db->table('flow_token_map')->where('flow_token', $flowToken)->delete();
    }

    /**
     * Booking completion previously only messaged the customer — the
     * business owner had no way of knowing a booking came in except by
     * checking the CRM. Sends a plain WhatsApp text to whatever number is
     * configured in Settings → Notifications; does nothing if unset.
     */
    private function notifyOwnerOfBooking(
        string $accountId,
        array  $waConfig,
        string $accessToken,
        ?array $type,
        array  $contact,
        string $scheduledAt,
        ?string $meetLink,
        bool   $isReschedule = false
    ): void {
        $account = (new AccountModel())->find($accountId);
        $prefs   = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $ownerNumber = trim($prefs['owner_whatsapp_number'] ?? '');

        if (!$ownerNumber || !PhoneUtils::isValid($ownerNumber)) {
            return;
        }

        $dateFormatted = date('D, d M Y', strtotime($scheduledAt));
        $timeFormatted = date('h:i A', strtotime($scheduledAt));

        $msg  = $isReschedule ? "🔄 *Appointment Rescheduled*\n\n" : "📅 *New Appointment Booked*\n\n";
        $msg .= "Service: {$type['name']}\n";
        $msg .= "Customer: {$contact['name']} ({$contact['phone']})\n";
        $msg .= "When: {$dateFormatted} at {$timeFormatted}\n";
        if ($meetLink) $msg .= "Meet: {$meetLink}\n";

        try {
            (new MetaApi())->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                PhoneUtils::normalize($ownerNumber),
                $msg
            );
        } catch (\Exception $e) {
            log_message('error', 'Owner booking notification failed: ' . $e->getMessage());
        }
    }

    private function markHotLead(string $conversationId, string $accountId): void
    {
        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) return;

        $hotLeadId = (new ConversationStatusModel())->ensureHotLeadExists($accountId);

        // Same code path as the manual Lead Status dropdown — logs the
        // system message and fires that status's configured auto-reply
        // (if the account set one up), so behavior stays identical
        // regardless of how the status got set.
        LeadStatusApplier::apply($conversationId, $hotLeadId, $accountId);
    }

    private function handleRebookRequest(array $contact, array $conversation, array $waConfig): void
    {
        $db      = \Config\Database::connect();
        $lastApt = $db->table('appointments')
            ->where('contact_id', $contact['id'])
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        $typeId = $lastApt['appointment_type_id'] ?? null;
        if (!$typeId) return;

        $flowRow = $db->table('whatsapp_flows')
            ->where('appointment_type_id', $typeId)
            ->where('status', 'published')
            ->get()->getRowArray();

        if (!$flowRow) return;

        $type        = (new \App\Models\AppointmentTypeModel())->find($typeId);
        $flowToken   = uniqid('rebook_', true);
        $accessToken = (new \App\Libraries\WhatsApp\Encryption())->decrypt($waConfig['access_token']);

        $db->table('flow_token_map')->insert([
            'flow_token'          => $flowToken,
            'account_id'          => $waConfig['account_id'],
            'appointment_type_id' => $typeId,
            'contact_id'          => $contact['id'],
            'conversation_id'     => $conversation['id'],
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        (new \App\Libraries\WhatsApp\MetaApi())->sendFlowMessage(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            "Book another appointment 📅",
            'Available Date & Time',
            $flowRow['flow_id'],
            $flowToken,
            [
                'min_date' => date('Y-m-d', strtotime('+1 day')),
                'max_date' => date('Y-m-d', strtotime('+' . ($type['max_days_ahead'] ?? 60) . ' days')),
            ]
        );
    }

    private function extractInteractiveResponse(array $interactive): string
    {
        if (isset($interactive['button_reply'])) return $interactive['button_reply']['title'];
        if (isset($interactive['list_reply']))   return $interactive['list_reply']['title'];
        if (isset($interactive['nfm_reply'])) {
            $data = json_decode($interactive['nfm_reply']['response_json'] ?? '{}', true) ?? [];
            if (!empty($data['selected_date']) && !empty($data['selected_time']) && $data['selected_time'] !== 'none') {
                $when = date('d M Y', strtotime($data['selected_date'])) . ' at ' . date('h:i A', strtotime($data['selected_date'] . ' ' . $data['selected_time']));
                return "Booking selected: {$when}";
            }
            return 'Flow response: ' . ($interactive['nfm_reply']['name'] ?? 'flow');
        }
        return 'Interactive response';
    }

    private function processStatusUpdate(array $status)
    {
        $messageModel = new MessageModel();
        $message      = $messageModel->where('whatsapp_message_id', $status['id'])->first();

        if (!$message) return;

        $updateData = ['status' => $status['status']];
        if ($status['status'] === 'failed' && isset($status['errors'])) {
            $updateData['error_message'] = json_encode($status['errors']);
        }
        $messageModel->update($message['id'], $updateData);

        $recipient = (new \App\Models\BroadcastRecipientModel())->where('whatsapp_message_id', $status['id'])->first();
        if ($recipient) {
            (new \App\Models\BroadcastRecipientModel())->update($recipient['id'], ['status' => $status['status']]);

            $broadcast   = (new \App\Models\BroadcastModel())->find($recipient['broadcast_id']);
            $countField  = match($status['status']) {
                'sent'      => 'sent_count',
                'delivered' => 'delivered_count',
                'read'      => 'read_count',
                'failed'    => 'failed_count',
                default     => null,
            };

            if ($broadcast && $countField) {
                (new \App\Models\BroadcastModel())->update($broadcast['id'], [
                    $countField => $broadcast[$countField] + 1,
                ]);
            }
        }
    }

    private function processTemplateStatus(array $event)
    {
        $metaTemplateId = $event['message_template_id'] ?? null;
        if (!$metaTemplateId) return;

        $template = (new MessageTemplateModel())->where('meta_template_id', $metaTemplateId)->first();
        if ($template) {
            (new MessageTemplateModel())->update($template['id'], ['status' => $event['event']]);
        }
    }
}
