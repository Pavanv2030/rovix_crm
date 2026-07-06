<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ConversationStatusModel;
use App\Models\ConversationModel;

class ConversationStatusesController extends BaseController
{
    public function index()
    {
        $statuses = (new ConversationStatusModel())
            ->where('account_id', session('account_id'))
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();

        return $this->response->setJSON($statuses);
    }

    public function store()
    {
        $name              = trim($this->request->getPost('name') ?? '');
        $color             = $this->request->getPost('color') ?? '#3B82F6';
        $autoReplyMessage  = trim($this->request->getPost('auto_reply_message') ?? '');
        $useAi             = (int) (bool) $this->request->getPost('use_ai');
        $aiInstruction     = trim($this->request->getPost('ai_instruction') ?? '');
        $replyMode         = $this->request->getPost('reply_mode') ?? 'static';
        $templateId        = $this->request->getPost('template_id') ?: null;
        $templateHeaderUrl = trim($this->request->getPost('template_header_url') ?? '');

        if (empty($name)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Status name is required']);
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#3B82F6';
        }
        if (!in_array($replyMode, ['static', 'ai', 'template'], true)) {
            $replyMode = 'static';
        }

        $model    = new ConversationStatusModel();
        $existing = $model->where('account_id', session('account_id'))->where('name', $name)->first();
        if ($existing) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'A status with this name already exists']);
        }

        $id = $model->insert([
            'account_id'         => session('account_id'),
            'name'               => $name,
            'color'              => $color,
            'auto_reply_message' => $autoReplyMessage ?: null,
            'reply_mode'         => $replyMode,
            'template_id'        => $templateId,
            'template_header_url' => $templateHeaderUrl ?: null,
            'use_ai'             => $useAi,
            'ai_instruction'     => $aiInstruction ?: null,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setStatusCode(201)->setJSON($model->find($id));
    }

    public function update(string $statusId)
    {
        $model  = new ConversationStatusModel();
        $status = $model->where('account_id', session('account_id'))->find($statusId);

        if (!$status) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Status not found']);
        }

        $name             = trim($this->request->getPost('name') ?? $status['name']);
        $color            = $this->request->getPost('color') ?? $status['color'];
        $autoReplyMessage = $this->request->getPost('auto_reply_message');
        $aiInstruction    = $this->request->getPost('ai_instruction');
        $useAi            = $this->request->getPost('use_ai');
        $replyMode        = $this->request->getPost('reply_mode');
        $templateId       = $this->request->getPost('template_id');
        $templateHeaderUrl = $this->request->getPost('template_header_url');

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = $status['color'];
        }
        if ($replyMode !== null && !in_array($replyMode, ['static', 'ai', 'template'], true)) {
            $replyMode = $status['reply_mode'];
        }

        $existing = $model->where('account_id', session('account_id'))->where('name', $name)->where('id !=', $statusId)->first();
        if ($existing) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'A status with this name already exists']);
        }

        $model->update($statusId, [
            'name'               => $name,
            'color'              => $color,
            'auto_reply_message' => $autoReplyMessage !== null ? (trim($autoReplyMessage) ?: null) : $status['auto_reply_message'],
            'reply_mode'         => $replyMode ?? $status['reply_mode'],
            'template_id'        => $templateId !== null ? ($templateId ?: null) : $status['template_id'],
            'template_header_url' => $templateHeaderUrl !== null ? (trim($templateHeaderUrl) ?: null) : $status['template_header_url'],
            'use_ai'             => $useAi !== null ? (int) (bool) $useAi : $status['use_ai'],
            'ai_instruction'     => $aiInstruction !== null ? (trim($aiInstruction) ?: null) : $status['ai_instruction'],
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON($model->find($statusId));
    }

    public function delete(string $statusId)
    {
        $model  = new ConversationStatusModel();
        $status = $model->where('account_id', session('account_id'))->find($statusId);

        if (!$status) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Status not found']);
        }

        // Conversations currently on this status fall back to none rather
        // than pointing at a deleted row.
        (new ConversationModel())->where('lead_status_id', $statusId)->update(null, ['lead_status_id' => null]);
        $model->delete($statusId);

        return $this->response->setJSON(['success' => true]);
    }
}
