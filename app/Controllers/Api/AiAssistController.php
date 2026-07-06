<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\MessageModel;
use App\Libraries\OpenAiClient;

class AiAssistController extends BaseController
{
    // Composer "Translate" button — translates the agent's draft reply into
    // whatever language the agent explicitly picks from the menu.
    public function translateOutgoing()
    {
        $text           = trim($this->request->getPost('text') ?? '');
        $targetLanguage = trim($this->request->getPost('target_language') ?? '');

        if (!$text) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Text required']);
        }
        if (!$targetLanguage) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Target language required']);
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => "Translate the agent's draft reply into {$targetLanguage}. Reply with ONLY the translated text — no explanation, no quotes, no language name.",
            ],
            ['role' => 'user', 'content' => $text],
        ];

        $result = OpenAiClient::chat(session('account_id'), $messages, null, 500, 'translate_outgoing');
        if (isset($result['error'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => $result['error']]);
        }

        return $this->response->setJSON(['success' => true, 'text' => $result['text']]);
    }

    // Composer "AI Rewrite" button — polishes the agent's draft to a more
    // professional tone without changing its language or meaning.
    public function rewrite()
    {
        $text = trim($this->request->getPost('text') ?? '');
        if (!$text) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Text required']);
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => 'Rewrite the given customer support message to sound more professional and polished, while keeping the same language, meaning, and intent. Reply with ONLY the rewritten text — no explanation, no quotes.',
            ],
            ['role' => 'user', 'content' => $text],
        ];

        $result = OpenAiClient::chat(session('account_id'), $messages, null, 500, 'rewrite');
        if (isset($result['error'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => $result['error']]);
        }

        return $this->response->setJSON(['success' => true, 'text' => $result['text']]);
    }

    // Per-message "Translate" link on incoming customer messages — translates
    // into whichever language the agent picks from the menu.
    public function translateIncoming()
    {
        $messageId      = $this->request->getPost('message_id');
        $targetLanguage = trim($this->request->getPost('target_language') ?? '');

        if (!$targetLanguage) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Target language required']);
        }

        // MessageModel is account-scoped by BaseModel — find() naturally
        // returns null for a message_id belonging to another account.
        $message = (new MessageModel())->find($messageId);
        if (!$message) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Message not found']);
        }

        $text = trim($message['content_text'] ?? '');
        if (!$text) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Nothing to translate']);
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => "Translate the following customer message into {$targetLanguage}. Reply with ONLY the translated text — no explanation, no quotes. If it is already in {$targetLanguage}, reply with it unchanged.",
            ],
            ['role' => 'user', 'content' => $text],
        ];

        $result = OpenAiClient::chat(session('account_id'), $messages, null, 500, 'translate_incoming');
        if (isset($result['error'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => $result['error']]);
        }

        return $this->response->setJSON(['success' => true, 'text' => $result['text']]);
    }
}
