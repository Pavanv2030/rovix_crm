<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\DealModel;
use App\Models\ProfileModel;
use App\Models\ContactModel;
use App\Models\WhatsAppConfigModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class DealsController extends BaseController
{
    public function move(string $dealId)
    {
        $input   = $this->request->getJSON(true) ?? $this->request->getPost();
        $stageId = $input['stage_id'] ?? null;

        if (!$stageId) return $this->response->setStatusCode(400)->setJSON(['error' => 'stage_id required']);

        $deal = (new DealModel())->find($dealId);
        if (!$deal) return $this->response->setStatusCode(404)->setJSON(['error' => 'Deal not found']);

        // Confirm the stage actually belongs to this deal's own pipeline —
        // without this check any stage_id (including one from another
        // account's pipeline) would be accepted as-is.
        $stage = \Config\Database::connect()->table('pipeline_stages')
            ->where('id', $stageId)
            ->where('pipeline_id', $deal['pipeline_id'])
            ->get()->getRowArray();
        if (!$stage) return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid stage']);

        (new DealModel())->update($dealId, ['stage_id' => $stageId, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->response->setJSON(['success' => true]);
    }

    public function assign(string $dealId)
    {
        $agentId = $this->request->getPost('agent_id');
        $deal    = (new DealModel())->find($dealId);
        if (!$deal) return $this->response->setStatusCode(404)->setJSON(['error' => 'Deal not found']);

        if ($agentId) {
            ProfileModel::setBypassAccountScope(true);
            $agent = (new ProfileModel())->where('user_id', $agentId)->where('account_id', session('account_id'))->first();
            ProfileModel::setBypassAccountScope(false);
            if (!$agent) return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid agent']);
        }

        (new DealModel())->update($dealId, ['assigned_agent_id' => $agentId ?: null, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->response->setJSON(['success' => true]);
    }

    public function updateValue(string $dealId)
    {
        $value = $this->request->getPost('value');
        $deal  = (new DealModel())->find($dealId);
        if (!$deal) return $this->response->setStatusCode(404)->setJSON(['error' => 'Deal not found']);

        (new DealModel())->update($dealId, ['value' => (float)$value, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->response->setJSON(['success' => true]);
    }

    public function sendWhatsApp(string $dealId)
    {
        $input   = $this->request->getJSON(true) ?? [];
        $message = trim($input['message'] ?? '');

        if (empty($message)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Message is required']);
        }

        $db   = \Config\Database::connect();
        $deal = $db->table('deals d')
            ->select('d.*, c.name as contact_name, c.phone_normalized')
            ->join('contacts c', 'c.id = d.contact_id', 'left')
            ->where('d.id', $dealId)
            ->where('d.account_id', session('account_id'))
            ->get()->getRowArray();

        if (!$deal) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Deal not found']);
        }

        if (empty($deal['contact_id'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No contact linked to this deal']);
        }

        if (empty($deal['phone_normalized'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Contact has no phone number']);
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if (!$waConfig || $waConfig['status'] !== 'connected') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'WhatsApp is not connected. Please configure it in Settings.']);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $response    = (new MetaApi())->sendText($waConfig['phone_number_id'], $accessToken, $deal['phone_normalized'], $message);

            $conversationModel = new ConversationModel();
            $conversation = $conversationModel->where('account_id', session('account_id'))->where('contact_id', $deal['contact_id'])->first();
            if (!$conversation) {
                $conversationId = $conversationModel->insert([
                    'account_id' => session('account_id'),
                    'contact_id' => $deal['contact_id'],
                    'status'     => 'open',
                ]);
                $conversation = $conversationModel->find($conversationId);
            }

            (new MessageModel())->insert([
                'conversation_id'     => $conversation['id'],
                'account_id'          => session('account_id'),
                'sender_type'         => 'agent',
                'content_type'        => 'text',
                'content_text'        => $message,
                'status'              => 'sent',
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);
            $conversationModel->update($conversation['id'], [
                'last_message_text' => mb_strimwidth(str_replace(["\n", "\r"], ' ', $message), 0, 100, '...'),
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (\Exception $e) {
            log_message('error', 'Deal WhatsApp send failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to send: ' . $e->getMessage()]);
        }
    }

    public function generateMessage(string $dealId)
    {
        $apiKey = env('openai.apiKey');
        if (empty($apiKey)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'OpenAI API key not configured. Add openai.apiKey to your .env file.']);
        }

        $db   = \Config\Database::connect();
        $deal = $db->table('deals d')
            ->select('d.title, d.value, d.status, d.notes, c.name as contact_name, ps.name as stage_name, pl.name as pipeline_name')
            ->join('contacts c', 'c.id = d.contact_id', 'left')
            ->join('pipeline_stages ps', 'ps.id = d.stage_id', 'left')
            ->join('pipelines pl', 'pl.id = d.pipeline_id', 'left')
            ->where('d.id', $dealId)
            ->where('d.account_id', session('account_id'))
            ->get()->getRowArray();

        if (!$deal) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Deal not found']);
        }

        $statusLabel = match($deal['status']) {
            'won'  => 'WON (successfully closed)',
            'lost' => 'LOST (not moving forward)',
            'open' => 'OPEN (in progress)',
            default => $deal['status'],
        };

        $prompt = "You are a professional sales representative sending a WhatsApp message to a customer. Write a brief, warm, and professional WhatsApp message based on the following deal details:\n\n"
            . "- Deal Title: {$deal['title']}\n"
            . "- Value: ₹" . number_format((float)$deal['value']) . "\n"
            . "- Status: {$statusLabel}\n"
            . "- Customer Name: " . ($deal['contact_name'] ?? 'the customer') . "\n"
            . "- Pipeline: " . ($deal['pipeline_name'] ?? '') . "\n"
            . "- Stage: " . ($deal['stage_name'] ?? '') . "\n\n"
            . "Instructions:\n"
            . "- Keep it 2-3 sentences, friendly but professional\n"
            . "- Use WhatsApp *bold* markdown sparingly for key info\n"
            . "- Do NOT include a subject line or greeting separately — write the full message\n"
            . "- Do NOT add placeholders like [Your Name] — end naturally\n"
            . "- Tailor tone to status: won=celebratory, lost=empathetic/hopeful, open=helpful";

        try {
            $client   = \Config\Services::curlrequest(['timeout' => 20]);
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 200,
                    'temperature' => 0.7,
                ],
            ]);

            $result  = json_decode($response->getBody(), true);
            $message = trim($result['choices'][0]['message']['content'] ?? '');

            if (empty($message)) {
                return $this->response->setStatusCode(500)->setJSON(['error' => 'AI returned empty response']);
            }

            return $this->response->setJSON(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            log_message('error', 'OpenAI generate message failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'AI generation failed: ' . $e->getMessage()]);
        }
    }

    public function stages(string $pipelineId)
    {
        $db       = \Config\Database::connect();
        $pipeline = $db->table('pipelines')->where('id', $pipelineId)->where('account_id', session('account_id'))->get()->getRowArray();
        if (!$pipeline) return $this->response->setStatusCode(404)->setJSON(['error' => 'Pipeline not found']);

        $stages = $db->table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON($stages);
    }
}
