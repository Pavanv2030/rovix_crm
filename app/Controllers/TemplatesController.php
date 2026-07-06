<?php

namespace App\Controllers;

use App\Models\MessageTemplateModel;
use App\Libraries\WhatsApp\TemplateSubmitter;

class TemplatesController extends BaseController
{
    public function index()
    {
        $model = new MessageTemplateModel();

        // Templates belong to a specific WABA. Switching connected WhatsApp
        // accounts must not surface a previous account's templates — filter
        // to whichever WABA is currently connected.
        $waConfig = (new \App\Models\WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if ($waConfig && !empty($waConfig['waba_id'])) {
            $model->where('waba_id', $waConfig['waba_id']);
        }

        $templates = $model->orderBy('created_at', 'DESC')->findAll();

        $statusCounts = ['all' => count($templates), 'draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($templates as $t) {
            if (isset($statusCounts[$t['status']])) $statusCounts[$t['status']]++;
        }

        return view('templates/index', [
            'pageTitle'    => 'Message Templates',
            'templates'    => $templates,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create()
    {
        return view('templates/create', ['pageTitle' => 'New Template']);
    }

    public function uploadMedia()
    {
        $file = $this->request->getFile('image');
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $this->response->setJSON(['error' => 'No valid file received.']);
        }

        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowed)) {
            return $this->response->setJSON(['error' => 'Only JPEG, PNG, WebP or GIF images are allowed.']);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->response->setJSON(['error' => 'Image must be under 5 MB.']);
        }

        $dir = FCPATH . 'uploads/template-media/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $newName = $file->getRandomName();
        $file->move($dir, $newName);

        return $this->response->setJSON([
            'url' => base_url('uploads/template-media/' . $newName),
        ]);
    }

    public function store()
    {
        $name = $this->request->getPost('name');
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            return redirect()->back()->withInput()->with('error', 'Template name must be lowercase letters, numbers and underscores only.');
        }

        $bodyText = $this->request->getPost('body_text');
        if (empty($bodyText)) {
            return redirect()->back()->withInput()->with('error', 'Body text is required.');
        }

        $language = $this->request->getPost('language') ?: 'en';
        $model    = new MessageTemplateModel();
        $existing = $model->where('account_id', session('account_id'))
            ->where('name', $name)
            ->where('language', $language)
            ->first();
        if ($existing) {
            return redirect()->back()->withInput()->with('error', "A template named \"{$name}\" ({$language}) already exists. Meta requires unique template names — edit the existing one or pick a different name.");
        }

        $buttons        = $this->sanitizeButtons($this->request->getPost('buttons'));
        $sampleValues   = $this->request->getPost('sample_values') ?: null;
        $headerType     = $this->request->getPost('header_type') ?: 'none';
        $carouselCards  = $headerType === 'carousel' ? ($this->request->getPost('carousel_cards') ?: null) : null;

        $waConfig = (new \App\Models\WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        $id    = $model->insert([
            'account_id'     => session('account_id'),
            'waba_id'        => $waConfig['waba_id'] ?? null,
            'name'           => $name,
            'language'       => $language,
            'category'       => $this->request->getPost('category') ?: 'utility',
            'header_type'    => $headerType,
            'header_content' => $carouselCards ?? ($this->request->getPost('header_content') ?: null),
            'body_text'      => $bodyText,
            'footer_text'    => $this->request->getPost('footer_text') ?: null,
            'buttons'        => $buttons,
            'sample_values'  => $sampleValues,
            'status'         => 'draft',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('templates/' . $id))->with('success', 'Template created.');
    }

    public function view(string $templateId)
    {
        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) return redirect()->to(base_url('templates'))->with('error', 'Template not found.');

        return view('templates/view', ['pageTitle' => $template['name'], 'template' => $template]);
    }

    public function edit(string $templateId)
    {
        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) return redirect()->to(base_url('templates'))->with('error', 'Template not found.');

        if (!in_array($template['status'], ['draft', 'rejected'])) {
            return redirect()->to(base_url('templates/' . $templateId))->with('error', 'Only draft or rejected templates can be edited.');
        }

        return view('templates/edit', ['pageTitle' => 'Edit Template', 'template' => $template]);
    }

    public function update(string $templateId)
    {
        $model    = new MessageTemplateModel();
        $template = $model->find($templateId);
        if (!$template) return redirect()->to(base_url('templates'))->with('error', 'Template not found.');

        if (!in_array($template['status'], ['draft', 'rejected'])) {
            return redirect()->back()->with('error', 'Cannot edit this template.');
        }

        $name = $this->request->getPost('name');
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            return redirect()->back()->withInput()->with('error', 'Template name must be lowercase letters, numbers and underscores only.');
        }

        $language = $this->request->getPost('language') ?: 'en';
        $existing = $model->where('account_id', session('account_id'))
            ->where('name', $name)
            ->where('language', $language)
            ->where('id !=', $templateId)
            ->first();
        if ($existing) {
            return redirect()->back()->withInput()->with('error', "A template named \"{$name}\" ({$language}) already exists. Meta requires unique template names — pick a different name.");
        }

        $model->update($templateId, [
            'name'           => $name,
            'language'       => $language,
            'category'       => $this->request->getPost('category') ?: 'utility',
            'header_type'    => $this->request->getPost('header_type') ?: 'none',
            'header_content' => $this->request->getPost('header_content') ?: null,
            'body_text'      => $this->request->getPost('body_text'),
            'footer_text'    => $this->request->getPost('footer_text') ?: null,
            'buttons'        => $this->sanitizeButtons($this->request->getPost('buttons')),
            'sample_values'  => $this->request->getPost('sample_values') ?: null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(base_url('templates/' . $templateId))->with('success', 'Template updated.');
    }

    /**
     * Trims stray whitespace from button text/url/phone values before
     * storage — a leading/trailing space makes Meta reject the whole
     * template submission with a cryptic "not a valid phone number" /
     * "not a valid URL" error.
     */
    private function sanitizeButtons(?string $buttonsJson): ?string
    {
        if (empty($buttonsJson)) return null;

        $buttons = json_decode($buttonsJson, true);
        if (!is_array($buttons)) return $buttonsJson;

        foreach ($buttons as &$btn) {
            foreach (['text', 'url', 'phone'] as $field) {
                if (isset($btn[$field]) && is_string($btn[$field])) {
                    $btn[$field] = trim($btn[$field]);
                }
            }
        }

        return json_encode($buttons);
    }

    public function submitForApproval(string $templateId)
    {
        $model    = new MessageTemplateModel();
        $template = $model->find($templateId);
        if (!$template) return redirect()->to(base_url('templates'))->with('error', 'Template not found.');

        if (!in_array($template['status'], ['draft', 'rejected'])) {
            return redirect()->back()->with('error', 'Only draft or rejected templates can be submitted.');
        }

        try {
            $submitter      = new TemplateSubmitter();
            $metaTemplateId = $submitter->submit($template, session('account_id'));

            $model->update($templateId, [
                'status'           => 'pending',
                'meta_template_id' => $metaTemplateId,
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

            return redirect()->to(base_url('templates/' . $templateId))->with('success', 'Template submitted for approval. Meta will review it within 24-48 hours.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Submission failed: ' . $e->getMessage());
        }
    }

    public function refreshStatus(string $templateId)
    {
        $model    = new MessageTemplateModel();
        $template = $model->find($templateId);
        if (!$template || empty($template['meta_template_id'])) {
            return redirect()->back()->with('error', 'No Meta template ID found.');
        }

        try {
            $waConfig = (new \App\Models\WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
            if (!$waConfig) throw new \Exception('WhatsApp not connected');

            $accessToken = (new \App\Libraries\WhatsApp\Encryption())->decrypt($waConfig['access_token']);
            $client      = \Config\Services::curlrequest(['timeout' => 15, 'http_errors' => false]);
            $response    = $client->get(
                "https://graph.facebook.com/" . \App\Libraries\WhatsApp\MetaApi::GRAPH_API_VERSION . "/{$template['meta_template_id']}?fields=status,quality_score",
                ['headers' => ['Authorization' => 'Bearer ' . $accessToken]]
            );

            $result = json_decode($response->getBody(), true);
            if ($response->getStatusCode() >= 400) {
                throw new \Exception($result['error']['message'] ?? ('HTTP ' . $response->getStatusCode()));
            }
            $status  = strtolower($result['status'] ?? $template['status']);
            $qRaw    = $result['quality_score'] ?? null;
            $quality = is_array($qRaw) ? strtolower($qRaw['score'] ?? 'unknown') : null;

            $model->update($templateId, [
                'status'        => $status,
                'quality_score' => $quality,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            return redirect()->to(base_url('templates/' . $templateId))->with('success', 'Status refreshed: ' . ucfirst($status));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Refresh failed: ' . $e->getMessage());
        }
    }

    public function delete(string $templateId)
    {
        if (!has_min_role('admin')) return redirect()->back()->with('error', 'Permission denied.');

        $model    = new MessageTemplateModel();
        $template = $model->find($templateId);
        if (!$template) return redirect()->to(base_url('templates'))->with('error', 'Template not found.');

        if (!in_array($template['status'], ['draft', 'rejected'])) {
            return redirect()->back()->with('error', 'Cannot delete approved or pending templates.');
        }

        $model->delete($templateId);
        return redirect()->to(base_url('templates'))->with('success', 'Template deleted.');
    }

    // ─── Fetch all templates from Meta ──────────────────────────────────────

    public function fetchFromMeta()
    {
        if (!has_min_role('admin')) {
            return $this->response->setJSON(['error' => 'Permission denied'])->setStatusCode(403);
        }

        try {
            $waConfig = (new \App\Models\WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
            if (!$waConfig || empty($waConfig['waba_id'])) {
                throw new \Exception('WhatsApp not connected or WABA ID missing.');
            }

            $accessToken = (new \App\Libraries\WhatsApp\Encryption())->decrypt($waConfig['access_token']);
            $wabaId      = $waConfig['waba_id'];
            $client      = \Config\Services::curlrequest(['timeout' => 30, 'http_errors' => false]);

            $synced = 0;
            $cursor = null;
            $maxPages = 10;

            for ($page = 0; $page < $maxPages; $page++) {
                $url = "https://graph.facebook.com/" . \App\Libraries\WhatsApp\MetaApi::GRAPH_API_VERSION . "/{$wabaId}/message_templates"
                     . "?fields=name,status,quality_score,language,category,components"
                     . "&limit=100"
                     . ($cursor ? "&after={$cursor}" : '');

                $resp   = $client->get($url, ['headers' => ['Authorization' => 'Bearer ' . $accessToken]]);
                $result = json_decode($resp->getBody(), true);

                if ($resp->getStatusCode() >= 400) {
                    throw new \Exception($result['error']['message'] ?? ('HTTP ' . $resp->getStatusCode()));
                }

                if (empty($result['data'])) break;

                foreach ($result['data'] as $mt) {
                    $this->upsertMetaTemplate($mt, $wabaId);
                    $synced++;
                }

                $cursor = $result['paging']['cursors']['after'] ?? null;
                if (!$cursor || empty($result['paging']['next'])) break;
            }

            return $this->response->setJSON(['success' => true, 'synced' => $synced]);

        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()])->setStatusCode(500);
        }
    }

    private function upsertMetaTemplate(array $mt, string $wabaId): void
    {
        $components = $mt['components'] ?? [];
        $headerType = 'none'; $headerContent = null;
        $bodyText   = ''; $footerText = null; $buttons = null;

        foreach ($components as $comp) {
            $type = strtoupper($comp['type'] ?? '');
            if ($type === 'HEADER') {
                $fmt = strtolower($comp['format'] ?? 'text');
                $headerType    = in_array($fmt, ['image', 'video', 'document']) ? $fmt : 'text';
                $headerContent = $comp['text'] ?? null;
            } elseif ($type === 'BODY') {
                $bodyText = $comp['text'] ?? '';
            } elseif ($type === 'FOOTER') {
                $footerText = $comp['text'] ?? null;
            } elseif ($type === 'BUTTONS') {
                $rawBtns = $comp['buttons'] ?? [];
                $mapped  = array_map(fn($b) => [
                    'type' => strtolower($b['type'] ?? 'quick_reply'),
                    'text' => $b['text'] ?? '',
                    'url'  => $b['url'] ?? null,
                ], $rawBtns);
                $buttons = json_encode($mapped);
            }
        }

        $status       = strtolower($mt['status'] ?? 'pending');
        $qualityScore = is_array($mt['quality_score'] ?? null)
            ? strtolower($mt['quality_score']['score'] ?? 'unknown')
            : null;
        $lang = strtolower(substr($mt['language'] ?? 'en', 0, 2));

        $model = new MessageTemplateModel();

        // Scope matches to this WABA — templates on different WABAs can
        // share a name (Meta's own sample "hello_world" is a common one),
        // so an unscoped name match risks overwriting the wrong account's row.
        $existing = $model->where('meta_template_id', $mt['id'])->where('waba_id', $wabaId)->first()
            ?? $model->where('name', $mt['name'])->where('waba_id', $wabaId)->first();

        $data = [
            'waba_id'          => $wabaId,
            'name'             => $mt['name'],
            'language'         => $lang,
            'category'         => strtolower($mt['category'] ?? 'marketing'),
            'header_type'      => $headerType,
            'header_content'   => $headerContent,
            'body_text'        => $bodyText ?: '(empty)',
            'footer_text'      => $footerText,
            'buttons'          => $buttons,
            'status'           => $status,
            'meta_template_id' => $mt['id'],
            'quality_score'    => $qualityScore,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $model->update($existing['id'], $data);
        } else {
            $model->insert(array_merge($data, [
                'account_id' => session('account_id'),
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    // ─── Template summary (stats + campaigns) ───────────────────────────────

    public function summary(string $templateId)
    {
        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) return redirect()->to(base_url('templates'))->with('error', 'Template not found.');

        $db = \Config\Database::connect();

        // Broadcasts that used this template
        $campaigns = $db->table('broadcasts')
            ->where('account_id', session('account_id'))
            ->where('template_name', $template['name'])
            ->orderBy('created_at', 'DESC')
            ->get()->getResultArray();

        // Aggregate totals
        $totals = [
            'campaigns'  => count($campaigns),
            'sent'       => array_sum(array_column($campaigns, 'sent_count')),
            'delivered'  => array_sum(array_column($campaigns, 'delivered_count')),
            'read'       => array_sum(array_column($campaigns, 'read_count')),
            'replied'    => array_sum(array_column($campaigns, 'replied_count')),
            'failed'     => array_sum(array_column($campaigns, 'failed_count')),
        ];

        return view('templates/summary', [
            'pageTitle' => $template['name'] . ' — Summary',
            'template'  => $template,
            'campaigns' => $campaigns,
            'totals'    => $totals,
        ]);
    }
}
