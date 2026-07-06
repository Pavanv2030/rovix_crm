<?php

namespace App\Controllers;

use App\Models\BroadcastModel;
use App\Models\BroadcastRecipientModel;
use App\Models\MessageTemplateModel;
use App\Models\TagModel;
use App\Models\ContactModel;
use App\Libraries\BroadcastProcessor;

class BroadcastsController extends BaseController
{
    public function index()
    {
        $model      = new BroadcastModel();
        $broadcasts = $model->orderBy('created_at', 'DESC')->findAll();

        $statusCounts = ['all' => count($broadcasts), 'draft' => 0, 'scheduled' => 0, 'sending' => 0, 'sent' => 0, 'cancelled' => 0];
        foreach ($broadcasts as $b) {
            if (isset($statusCounts[$b['status']])) $statusCounts[$b['status']]++;
        }

        $templates = (new MessageTemplateModel())->where('status', 'approved')->orderBy('name', 'ASC')->findAll();

        return view('broadcasts/index', [
            'pageTitle'    => 'Broadcasts',
            'broadcasts'   => $broadcasts,
            'statusCounts' => $statusCounts,
            'templates'    => $templates,
        ]);
    }

    public function create()
    {
        $templates = (new MessageTemplateModel())->where('status', 'approved')->orderBy('name', 'ASC')->findAll();
        $tags      = (new TagModel())->orderBy('name', 'ASC')->findAll();

        return view('broadcasts/create', [
            'pageTitle' => 'New Broadcast',
            'templates' => $templates,
            'tags'      => $tags,
        ]);
    }

    public function store()
    {
        $name         = trim($this->request->getPost('name') ?? '');
        $templateName = $this->request->getPost('template_name');

        if (empty($name)) {
            return redirect()->back()->withInput()->with('error', 'Campaign name is required.');
        }
        if (empty($templateName)) {
            return redirect()->back()->withInput()->with('error', 'Please select a template.');
        }

        $headerMediaUrl = trim($this->request->getPost('header_media_url') ?? '');
        $templateCheck  = (new MessageTemplateModel())->where('name', $templateName)->where('status', 'approved')->first();
        if ($templateCheck && in_array($templateCheck['header_type'] ?? 'none', ['image', 'video', 'document'], true) && !$headerMediaUrl) {
            return redirect()->back()->withInput()->with('error', 'This template has a media header — a header URL is required.');
        }

        $audienceType = $this->request->getPost('audience_type') ?? 'all';
        $tagIds       = $this->request->getPost('tag_ids') ?? [];

        $audienceFilter = ['type' => $audienceType];
        if ($audienceType === 'tags') {
            $audienceFilter['tag_ids'] = array_filter((array) $tagIds);
        }

        // Variable map: {1: 'name', 2: 'company', ...}
        $varSources = $this->request->getPost('var_source') ?? [];
        $variableMap = [];
        foreach ($varSources as $idx => $source) {
            if (!empty($source)) $variableMap[(string) $idx] = $source;
        }

        $templateLanguage = $templateCheck['language'] ?? 'en';

        $model = new BroadcastModel();
        $id = $model->insert([
            'account_id'        => session('account_id'),
            'name'              => $name,
            'template_name'     => $templateName,
            'template_language' => $templateLanguage,
            'header_media_url'  => $headerMediaUrl ?: null,
            'audience_filter'   => json_encode($audienceFilter),
            'variable_map'      => !empty($variableMap) ? json_encode($variableMap) : null,
            'batch_size'        => max(1, min(50, (int) ($this->request->getPost('batch_size') ?? 50))),
            'status'            => 'draft',
            'sent_count'        => 0,
            'delivered_count'   => 0,
            'read_count'        => 0,
            'replied_count'     => 0,
            'failed_count'      => 0,
            'total_recipients'  => 0,
            'created_by'        => session('user_id'),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('broadcasts/' . $id))->with('success', 'Broadcast created as draft.');
    }

    public function view(string $broadcastId)
    {
        $broadcast = (new BroadcastModel())->find($broadcastId);
        if (!$broadcast) return redirect()->to(base_url('broadcasts'))->with('error', 'Broadcast not found.');

        $recipientModel = new BroadcastRecipientModel();
        $statusFilter   = $this->request->getGet('status') ?? 'all';

        $rQuery = $recipientModel->where('broadcast_id', $broadcastId);
        if ($statusFilter !== 'all') $rQuery->where('status', $statusFilter);

        $page       = (int) ($this->request->getGet('page') ?? 1);
        $perPage    = 50;
        $offset     = ($page - 1) * $perPage;
        $recipients = $rQuery->orderBy('created_at', 'ASC')->findAll($perPage, $offset);
        $totalRec   = (new BroadcastRecipientModel())->where('broadcast_id', $broadcastId)->countAllResults();

        // Enrich with contact info
        $contactModel = new ContactModel();
        foreach ($recipients as &$r) {
            $c = $contactModel->find($r['contact_id']);
            $r['contact_name']  = $c['name'] ?? null;
            $r['contact_phone'] = $c['phone'] ?? null;
        }
        unset($r);

        $stats = [
            'total'     => (int) $broadcast['total_recipients'],
            'sent'      => (int) $broadcast['sent_count'],
            'delivered' => (int) $broadcast['delivered_count'],
            'read'      => (int) $broadcast['read_count'],
            'replied'   => (int) $broadcast['replied_count'],
            'failed'    => (int) $broadcast['failed_count'],
            'pending'   => max(0, (int) $broadcast['total_recipients'] - (int) $broadcast['sent_count'] - (int) $broadcast['failed_count']),
        ];

        $pct = $stats['total'] > 0 ? round(($stats['sent'] + $stats['failed']) / $stats['total'] * 100) : 0;

        return view('broadcasts/view', [
            'pageTitle'    => $broadcast['name'],
            'broadcast'    => $broadcast,
            'recipients'   => $recipients,
            'stats'        => $stats,
            'pct'          => $pct,
            'statusFilter' => $statusFilter,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalRec'     => $totalRec,
        ]);
    }

    public function edit(string $broadcastId)
    {
        $broadcast = (new BroadcastModel())->find($broadcastId);
        if (!$broadcast) return redirect()->to(base_url('broadcasts'))->with('error', 'Not found.');
        if ($broadcast['status'] !== 'draft') {
            return redirect()->to(base_url('broadcasts/' . $broadcastId))->with('error', 'Only draft broadcasts can be edited.');
        }

        $templates = (new MessageTemplateModel())->where('status', 'approved')->orderBy('name', 'ASC')->findAll();
        $tags      = (new TagModel())->orderBy('name', 'ASC')->findAll();

        return view('broadcasts/edit', [
            'pageTitle' => 'Edit Broadcast',
            'broadcast' => $broadcast,
            'templates' => $templates,
            'tags'      => $tags,
        ]);
    }

    public function update(string $broadcastId)
    {
        $model     = new BroadcastModel();
        $broadcast = $model->find($broadcastId);
        if (!$broadcast || $broadcast['status'] !== 'draft') {
            return redirect()->back()->with('error', 'Cannot update this broadcast.');
        }

        $name         = trim($this->request->getPost('name') ?? '');
        $templateName = $this->request->getPost('template_name');
        if (empty($name) || empty($templateName)) {
            return redirect()->back()->withInput()->with('error', 'Name and template are required.');
        }

        $audienceType   = $this->request->getPost('audience_type') ?? 'all';
        $tagIds         = $this->request->getPost('tag_ids') ?? [];
        $audienceFilter = ['type' => $audienceType];
        if ($audienceType === 'tags') $audienceFilter['tag_ids'] = array_filter((array) $tagIds);

        $varSources  = $this->request->getPost('var_source') ?? [];
        $variableMap = [];
        foreach ($varSources as $idx => $source) {
            if (!empty($source)) $variableMap[(string) $idx] = $source;
        }

        $template = (new MessageTemplateModel())->where('name', $templateName)->where('status', 'approved')->first();

        $headerMediaUrl = trim($this->request->getPost('header_media_url') ?? '');
        if ($template && in_array($template['header_type'] ?? 'none', ['image', 'video', 'document'], true) && !$headerMediaUrl) {
            return redirect()->back()->withInput()->with('error', 'This template has a media header — a header URL is required.');
        }

        $model->update($broadcastId, [
            'name'              => $name,
            'template_name'     => $templateName,
            'template_language' => $template['language'] ?? 'en',
            'header_media_url'  => $headerMediaUrl ?: null,
            'audience_filter'   => json_encode($audienceFilter),
            'variable_map'      => !empty($variableMap) ? json_encode($variableMap) : null,
            'batch_size'        => max(1, min(50, (int) ($this->request->getPost('batch_size') ?? 50))),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('broadcasts/' . $broadcastId))->with('success', 'Broadcast updated.');
    }

    public function schedule(string $broadcastId)
    {
        $model     = new BroadcastModel();
        $broadcast = $model->find($broadcastId);
        if (!$broadcast || $broadcast['status'] !== 'draft') {
            return redirect()->back()->with('error', 'Only draft broadcasts can be scheduled.');
        }

        $scheduledAt = $this->request->getPost('scheduled_at');
        if (empty($scheduledAt) || strtotime($scheduledAt) <= time()) {
            return redirect()->back()->with('error', 'Scheduled time must be in the future.');
        }

        $model->update($broadcastId, [
            'status'       => 'scheduled',
            'scheduled_at' => date('Y-m-d H:i:s', strtotime($scheduledAt)),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('broadcasts/' . $broadcastId))
            ->with('success', 'Broadcast scheduled for ' . date('d M Y, g:i A', strtotime($scheduledAt)));
    }

    public function sendNow(string $broadcastId)
    {
        $broadcast = (new BroadcastModel())->find($broadcastId);
        if (!$broadcast || !in_array($broadcast['status'], ['draft', 'scheduled'])) {
            return redirect()->back()->with('error', 'Cannot send this broadcast.');
        }

        try {
            $processor  = new BroadcastProcessor();
            $batchCount = $processor->prepare($broadcastId);
            return redirect()->to(base_url('broadcasts/' . $broadcastId))
                ->with('success', "Broadcast queued in {$batchCount} batch(es). Sending now...");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send: ' . $e->getMessage());
        }
    }

    public function cancel(string $broadcastId)
    {
        $model     = new BroadcastModel();
        $broadcast = $model->find($broadcastId);
        if (!$broadcast || $broadcast['status'] !== 'scheduled') {
            return redirect()->back()->with('error', 'Only scheduled broadcasts can be cancelled.');
        }

        $model->update($broadcastId, [
            'status'       => 'draft',
            'scheduled_at' => null,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('broadcasts/' . $broadcastId))->with('success', 'Broadcast unscheduled and set back to draft.');
    }

    public function export(string $broadcastId)
    {
        $broadcast = (new BroadcastModel())->find($broadcastId);
        if (!$broadcast) return redirect()->to(base_url('broadcasts'))->with('error', 'Not found.');

        $csv      = (new \App\Libraries\BroadcastExporter())->exportToCsv($broadcastId);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $broadcast['name']);
        $filename = 'broadcast_' . $safeName . '_' . date('Y-m-d') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    public function retryFailed(string $broadcastId)
    {
        $model     = new BroadcastModel();
        $broadcast = $model->find($broadcastId);
        if (!$broadcast) return redirect()->to(base_url('broadcasts'))->with('error', 'Not found.');

        $failedRecipients = (new BroadcastRecipientModel())->where('broadcast_id', $broadcastId)->where('status', 'failed')->findAll();
        if (empty($failedRecipients)) {
            return redirect()->back()->with('error', 'No failed recipients to retry.');
        }

        // Create a new broadcast cloned from the original
        $newId = $model->insert([
            'account_id'        => $broadcast['account_id'],
            'name'              => $broadcast['name'] . ' (Retry)',
            'template_name'     => $broadcast['template_name'],
            'template_language' => $broadcast['template_language'],
            'audience_filter'   => $broadcast['audience_filter'],
            'variable_map'      => $broadcast['variable_map'],
            'batch_size'        => $broadcast['batch_size'] ?? 50,
            'status'            => 'sending',
            'sent_count'        => 0,
            'delivered_count'   => 0,
            'read_count'        => 0,
            'replied_count'     => 0,
            'failed_count'      => 0,
            'total_recipients'  => count($failedRecipients),
            'created_by'        => session('user_id'),
            'sent_at'           => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        $recipientModel = new BroadcastRecipientModel();
        $dispatcher     = new \App\Libraries\JobDispatcher();
        $recipientIds   = [];

        foreach ($failedRecipients as $r) {
            $recipientIds[] = $recipientModel->insert([
                'broadcast_id' => $newId,
                'contact_id'   => $r['contact_id'],
                'variables'    => $r['variables'],
                'status'       => 'pending',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        $batchSize = max(1, min(50, (int) ($broadcast['batch_size'] ?? 50)));
        foreach (array_chunk($recipientIds, $batchSize) as $i => $batch) {
            $dispatcher->dispatch('send_broadcast_batch', [
                'broadcast_id'  => $newId,
                'recipient_ids' => $batch,
                'batch_index'   => $i,
            ], null, 7);
        }

        return redirect()->to(base_url('broadcasts/' . $newId))->with('success', count($failedRecipients) . ' failed recipients queued for retry.');
    }

    public function duplicate(string $broadcastId)
    {
        $model     = new BroadcastModel();
        $broadcast = $model->find($broadcastId);
        if (!$broadcast) return redirect()->to(base_url('broadcasts'))->with('error', 'Not found.');

        $newId = $model->insert([
            'account_id'        => $broadcast['account_id'],
            'name'              => $broadcast['name'] . ' (Copy)',
            'template_name'     => $broadcast['template_name'],
            'template_language' => $broadcast['template_language'],
            'audience_filter'   => $broadcast['audience_filter'],
            'variable_map'      => $broadcast['variable_map'],
            'batch_size'        => $broadcast['batch_size'] ?? 50,
            'status'            => 'draft',
            'sent_count'        => 0,
            'delivered_count'   => 0,
            'read_count'        => 0,
            'replied_count'     => 0,
            'failed_count'      => 0,
            'total_recipients'  => 0,
            'created_by'        => session('user_id'),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('broadcasts/' . $newId . '/edit'))->with('success', 'Broadcast duplicated. Edit and send when ready.');
    }

    public function delete(string $broadcastId)
    {
        if (!has_min_role('admin')) return redirect()->back()->with('error', 'Permission denied.');

        $model     = new BroadcastModel();
        $broadcast = $model->find($broadcastId);
        if (!$broadcast || $broadcast['status'] !== 'draft') {
            return redirect()->back()->with('error', 'Only draft broadcasts can be deleted.');
        }

        (new BroadcastRecipientModel())->where('broadcast_id', $broadcastId)->delete();
        $model->delete($broadcastId);

        return redirect()->to(base_url('broadcasts'))->with('success', 'Broadcast deleted.');
    }
}
