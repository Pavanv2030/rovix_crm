<?php

namespace App\Controllers;

use App\Models\DealModel;
use App\Models\PipelineModel;
use App\Models\ContactModel;
use App\Models\ProfileModel;
use App\Models\WhatsAppConfigModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class DealsController extends BaseController
{
    public function create()
    {
        $db        = \Config\Database::connect();
        $pipelines = (new PipelineModel())->findAll();

        foreach ($pipelines as &$p) {
            $p['stages'] = $db->table('pipeline_stages')
                ->where('pipeline_id', $p['id'])
                ->orderBy('position')->get()->getResultArray();
        }

        $contacts = (new ContactModel())->orderBy('name')->findAll();

        ProfileModel::setBypassAccountScope(true);
        $agents = (new ProfileModel())->where('account_id', session('account_id'))
            ->whereIn('account_role', ['owner', 'admin', 'agent'])->findAll();
        ProfileModel::setBypassAccountScope(false);

        $selectedStageId    = $this->request->getGet('stage_id');
        $selectedPipelineId = $this->request->getGet('pipeline_id');

        return view('deals/create', [
            'pageTitle'          => 'New Deal',
            'pipelines'          => $pipelines,
            'contacts'           => $contacts,
            'agents'             => $agents,
            'selectedStageId'    => $selectedStageId,
            'selectedPipelineId' => $selectedPipelineId,
        ]);
    }

    public function store()
    {
        $title      = trim($this->request->getPost('title') ?? '');
        $pipelineId = $this->request->getPost('pipeline_id');
        $stageId    = $this->request->getPost('stage_id');

        if (empty($title) || empty($pipelineId) || empty($stageId)) {
            return redirect()->back()->withInput()->with('error', 'Title, pipeline and stage are required.');
        }

        $pipeline = (new PipelineModel())->find($pipelineId);
        if (!$pipeline) return redirect()->back()->with('error', 'Invalid pipeline.');

        $dealModel = new DealModel();
        $dealId    = $dealModel->insert([
            'account_id'          => session('account_id'),
            'pipeline_id'         => $pipelineId,
            'stage_id'            => $stageId,
            'contact_id'          => $this->request->getPost('contact_id') ?: null,
            'title'               => $title,
            'value'               => (float)($this->request->getPost('value') ?? 0),
            'currency'            => $this->request->getPost('currency') ?? 'INR',
            'status'              => 'open',
            'expected_close_date' => $this->request->getPost('expected_close_date') ?: null,
            'assigned_agent_id'   => $this->request->getPost('assigned_agent_id') ?: null,
            'notes'               => $this->request->getPost('notes') ?: null,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $redirect = $this->request->getPost('redirect_to') ?: base_url('pipelines/' . $pipelineId . '/board');
        return redirect()->to($redirect)->with('success', 'Deal created!');
    }

    public function view(string $dealId)
    {
        $db   = \Config\Database::connect();
        $deal = $db->table('deals d')
            ->select('d.*, c.name as contact_name, c.phone as contact_phone, p.full_name as agent_name, ps.name as stage_name, ps.color as stage_color, pl.name as pipeline_name')
            ->join('contacts c', 'c.id = d.contact_id', 'left')
            ->join('profiles p', 'p.user_id = d.assigned_agent_id', 'left')
            ->join('pipeline_stages ps', 'ps.id = d.stage_id', 'left')
            ->join('pipelines pl', 'pl.id = d.pipeline_id', 'left')
            ->where('d.id', $dealId)
            ->where('d.account_id', session('account_id'))
            ->get()->getRowArray();

        if (!$deal) return redirect()->to(base_url('pipelines'))->with('error', 'Deal not found.');

        return view('deals/view', ['pageTitle' => $deal['title'], 'deal' => $deal]);
    }

    public function edit(string $dealId)
    {
        $deal = (new DealModel())->find($dealId);
        if (!$deal) return redirect()->to(base_url('pipelines'))->with('error', 'Deal not found.');

        $db        = \Config\Database::connect();
        $pipelines = (new PipelineModel())->findAll();
        foreach ($pipelines as &$p) {
            $p['stages'] = $db->table('pipeline_stages')->where('pipeline_id', $p['id'])->orderBy('position')->get()->getResultArray();
        }

        $contacts = (new ContactModel())->orderBy('name')->findAll();

        ProfileModel::setBypassAccountScope(true);
        $agents = (new ProfileModel())->where('account_id', session('account_id'))
            ->whereIn('account_role', ['owner', 'admin', 'agent'])->findAll();
        ProfileModel::setBypassAccountScope(false);

        return view('deals/edit', [
            'pageTitle' => 'Edit Deal',
            'deal'      => $deal,
            'pipelines' => $pipelines,
            'contacts'  => $contacts,
            'agents'    => $agents,
        ]);
    }

    public function update(string $dealId)
    {
        $dealModel = new DealModel();
        $deal      = $dealModel->find($dealId);
        if (!$deal) return redirect()->to(base_url('pipelines'))->with('error', 'Deal not found.');

        $pipelineId = $this->request->getPost('pipeline_id');
        if ($pipelineId && !(new PipelineModel())->find($pipelineId)) {
            return redirect()->back()->withInput()->with('error', 'Invalid pipeline.');
        }

        $dealModel->update($dealId, [
            'title'               => trim($this->request->getPost('title')),
            'pipeline_id'         => $pipelineId,
            'stage_id'            => $this->request->getPost('stage_id'),
            'contact_id'          => $this->request->getPost('contact_id') ?: null,
            'value'               => (float)($this->request->getPost('value') ?? 0),
            'currency'            => $this->request->getPost('currency') ?? 'INR',
            'expected_close_date' => $this->request->getPost('expected_close_date') ?: null,
            'assigned_agent_id'   => $this->request->getPost('assigned_agent_id') ?: null,
            'notes'               => $this->request->getPost('notes') ?: null,
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('deals/' . $dealId))->with('success', 'Deal updated.');
    }

    public function updateStatus(string $dealId)
    {
        $status = $this->request->getPost('status');
        if (!in_array($status, ['won', 'lost', 'open'])) {
            return redirect()->back()->with('error', 'Invalid status.');
        }

        $deal = (new DealModel())->find($dealId);
        if (!$deal) return redirect()->to(base_url('pipelines'))->with('error', 'Deal not found.');

        (new DealModel())->update($dealId, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->notifyContactOnStatusChange($deal, $status);

        return redirect()->to(base_url('deals/' . $dealId))->with('success', 'Deal marked as ' . $status . '.');
    }

    private function notifyContactOnStatusChange(array $deal, string $newStatus): void
    {
        if (empty($deal['contact_id'])) return;

        $contact = (new ContactModel())->find($deal['contact_id']);
        if (!$contact || empty($contact['phone_normalized'])) return;

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $deal['account_id'])->first();
        if (!$waConfig || $waConfig['status'] !== 'connected') return;

        $contactName = $contact['name'] ?? 'there';
        $dealTitle   = $deal['title'];
        $value       = '₹' . number_format((float)$deal['value']);

        $message = match($newStatus) {
            'won'  => "Hi {$contactName}! 🎉 Great news — your deal *{$dealTitle}* ({$value}) has been successfully closed. Thank you for choosing us! We look forward to working with you.",
            'lost' => "Hi {$contactName}, we wanted to follow up regarding *{$dealTitle}*. Unfortunately we weren't able to move forward at this time, but we'd love to reconnect in the future. Feel free to reach out anytime!",
            'open' => "Hi {$contactName}, just a quick update — your deal *{$dealTitle}* has been reopened. Our team will be in touch with you shortly.",
            default => null,
        };

        if (!$message) return;

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $response    = (new MetaApi())->sendText($waConfig['phone_number_id'], $accessToken, $contact['phone_normalized'], $message);

            $conversationModel = new ConversationModel();
            $conversation = $conversationModel->where('account_id', $deal['account_id'])->where('contact_id', $contact['id'])->first();
            if (!$conversation) {
                $conversationId = $conversationModel->insert([
                    'account_id' => $deal['account_id'],
                    'contact_id' => $contact['id'],
                    'status'     => 'open',
                ]);
                $conversation = $conversationModel->find($conversationId);
            }

            (new MessageModel())->insert([
                'conversation_id'     => $conversation['id'],
                'account_id'          => $deal['account_id'],
                'sender_type'         => 'bot',
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
        } catch (\Exception $e) {
            log_message('error', 'Deal status WhatsApp notify failed: ' . $e->getMessage());
        }
    }

    public function delete(string $dealId)
    {
        if (!has_min_role('admin')) return redirect()->back()->with('error', 'Permission denied.');

        $deal = (new DealModel())->find($dealId);
        if (!$deal) return redirect()->to(base_url('pipelines'))->with('error', 'Deal not found.');

        $pipelineId = $deal['pipeline_id'];
        (new DealModel())->delete($dealId);

        return redirect()->to(base_url('pipelines/' . $pipelineId . '/board'))->with('success', 'Deal deleted.');
    }
}
