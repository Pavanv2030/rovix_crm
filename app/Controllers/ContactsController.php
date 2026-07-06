<?php

namespace App\Controllers;

use App\Models\ContactModel;
use App\Models\TagModel;
use App\Models\ContactTagModel;
use App\Models\CustomFieldModel;
use App\Models\ContactCustomValueModel;
use App\Models\ContactNoteModel;
use App\Models\ConversationModel;
use App\Models\DealModel;
use App\Models\ProfileModel;
use App\Libraries\WhatsApp\PhoneUtils;

class ContactsController extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();

        // Load contacts with agent name + counts
        $contacts = $db->table('contacts c')
            ->select('c.*, p.full_name as agent_name,
                (SELECT COUNT(*) FROM conversations WHERE contact_id = c.id) as conversation_count,
                (SELECT COUNT(*) FROM deals WHERE contact_id = c.id) as deal_count,
                (SELECT COALESCE(SUM(value),0) FROM deals WHERE contact_id = c.id AND status = "won") as deal_value_won')
            ->join('profiles p', 'p.user_id = c.assigned_agent_id', 'left')
            ->where('c.account_id', session('account_id'))
            ->orderBy('c.created_at', 'DESC')
            ->limit(50)
            ->get()->getResultArray();

        // Load tags for all contacts in one query
        $contactIds = array_column($contacts, 'id');
        $tagsMap    = [];
        foreach ($contactIds as $cid) $tagsMap[$cid] = [];

        if ($contactIds) {
            $tagRows = $db->table('contact_tags ct')
                ->select('ct.contact_id, t.id, t.name, t.color')
                ->join('tags t', 't.id = ct.tag_id')
                ->whereIn('ct.contact_id', $contactIds)
                ->get()->getResultArray();
            foreach ($tagRows as $row) {
                $tagsMap[$row['contact_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'color' => $row['color']];
            }
        }

        foreach ($contacts as &$contact) {
            $contact['tags'] = $tagsMap[$contact['id']] ?? [];
        }

        $allTags    = (new TagModel())->findAll();
        $totalCount = $db->table('contacts')->where('account_id', session('account_id'))->countAllResults();

        return view('contacts/index', [
            'pageTitle'  => 'Contacts',
            'contacts'   => $contacts,
            'allTags'    => $allTags,
            'totalCount' => $totalCount,
        ]);
    }

    public function view(string $contactId)
    {
        $db      = \Config\Database::connect();
        $contact = (new ContactModel())->find($contactId);

        if (!$contact) {
            return redirect()->to(base_url('contacts'))->with('error', 'Contact not found.');
        }

        // Tags
        $tags = $db->table('contact_tags ct')
            ->select('t.id, t.name, t.color')
            ->join('tags t', 't.id = ct.tag_id')
            ->where('ct.contact_id', $contactId)
            ->get()->getResultArray();

        // Custom fields with values
        $customFields = $db->table('custom_fields cf')
            ->select('cf.*, ccv.value as field_value')
            ->join('contact_custom_values ccv', 'ccv.custom_field_id = cf.id AND ccv.contact_id = ' . $db->escape($contactId), 'left')
            ->where('cf.account_id', session('account_id'))
            ->get()->getResultArray();

        // Notes with author info
        $notes = $db->table('contact_notes cn')
            ->select('cn.*, p.full_name as author_name, p.avatar_url as author_avatar')
            ->join('profiles p', 'p.user_id = cn.user_id', 'left')
            ->where('cn.contact_id', $contactId)
            ->orderBy('cn.created_at', 'DESC')
            ->get()->getResultArray();

        // Recent conversations
        $conversations = (new ConversationModel())
            ->where('contact_id', $contactId)
            ->orderBy('last_message_at', 'DESC')
            ->limit(10)->findAll();

        // Deals
        $deals = (new DealModel())->where('contact_id', $contactId)->findAll();

        // Stats
        $stats = [
            'total_conversations' => count($conversations),
            'open_deals'          => count(array_filter($deals, fn($d) => $d['status'] === 'open')),
            'open_deal_value'     => array_sum(array_column(array_filter($deals, fn($d) => $d['status'] === 'open'), 'value')),
            'won_deals'           => count(array_filter($deals, fn($d) => $d['status'] === 'won')),
            'won_deal_value'      => array_sum(array_column(array_filter($deals, fn($d) => $d['status'] === 'won'), 'value')),
            'first_contact'       => $contact['created_at'],
        ];

        return view('contacts/view', [
            'pageTitle'    => $contact['name'] ?? $contact['phone'],
            'contact'      => $contact,
            'tags'         => $tags,
            'customFields' => $customFields,
            'notes'        => $notes,
            'conversations' => $conversations,
            'deals'        => $deals,
            'stats'        => $stats,
        ]);
    }

    public function create()
    {
        $allTags      = (new TagModel())->findAll();
        $customFields = (new CustomFieldModel())->where('account_id', session('account_id'))->findAll();

        ProfileModel::setBypassAccountScope(true);
        $agents = (new ProfileModel())->where('account_id', session('account_id'))
            ->whereIn('account_role', ['owner', 'admin', 'agent'])->findAll();
        ProfileModel::setBypassAccountScope(false);

        return view('contacts/create', [
            'pageTitle'    => 'New Contact',
            'allTags'      => $allTags,
            'customFields' => $customFields,
            'agents'       => $agents,
        ]);
    }

    public function store()
    {
        $phone = $this->request->getPost('phone');
        if (empty($phone)) {
            return redirect()->back()->withInput()->with('error', 'Phone number is required.');
        }

        $phoneNormalized = PhoneUtils::normalize($phone);
        if (!PhoneUtils::isValid($phoneNormalized)) {
            return redirect()->back()->withInput()->with('error', 'Invalid phone number format.');
        }

        // Duplicate check
        $existing = (new ContactModel())
            ->where('account_id', session('account_id'))
            ->where('phone_normalized', $phoneNormalized)
            ->first();

        if ($existing) {
            return redirect()->back()->withInput()->with('error', 'A contact with this phone number already exists.');
        }

        $contactModel = new ContactModel();
        $contactId    = $contactModel->insert([
            'account_id'        => session('account_id'),
            'phone'             => $phone,
            'phone_normalized'  => $phoneNormalized,
            'name'              => $this->request->getPost('name') ?: null,
            'email'             => $this->request->getPost('email') ?: null,
            'company'           => $this->request->getPost('company') ?: null,
            'channel'           => $this->request->getPost('channel') ?: null,
            'vertical'          => $this->request->getPost('vertical') ?: null,
            'status'            => $this->request->getPost('status') ?: 'New',
            'assigned_agent_id'  => $this->request->getPost('assigned_agent_id') ?: null,
            'follow_up_date'     => $this->request->getPost('follow_up_date') ?: null,
            'is_phone_verified'  => $this->request->getPost('is_phone_verified') === '1' ? 1 : 0,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        // Tags
        $tagIds = $this->request->getPost('tag_ids') ?? [];
        if ($tagIds) {
            $ctModel = new ContactTagModel();
            foreach ($tagIds as $tagId) {
                $ctModel->insert(['contact_id' => $contactId, 'tag_id' => $tagId]);
            }
        }

        // Custom fields
        $customValues = $this->request->getPost('custom_fields') ?? [];
        if ($customValues) {
            $cfvModel = new ContactCustomValueModel();
            foreach ($customValues as $fieldId => $value) {
                if ($value !== '' && $value !== null) {
                    $cfvModel->insert(['contact_id' => $contactId, 'custom_field_id' => $fieldId, 'value' => $value]);
                }
            }
        }

        return redirect()->to(base_url('contacts/' . $contactId))->with('success', 'Contact created successfully.');
    }

    public function edit(string $contactId)
    {
        $contact = (new ContactModel())->find($contactId);
        if (!$contact) {
            return redirect()->to(base_url('contacts'))->with('error', 'Contact not found.');
        }

        $db   = \Config\Database::connect();
        $tags = $db->table('contact_tags')->select('tag_id')->where('contact_id', $contactId)->get()->getResultArray();
        $selectedTagIds = array_column($tags, 'tag_id');

        $customFields = $db->table('custom_fields cf')
            ->select('cf.*, ccv.value as field_value')
            ->join('contact_custom_values ccv', 'ccv.custom_field_id = cf.id AND ccv.contact_id = ' . $db->escape($contactId), 'left')
            ->where('cf.account_id', session('account_id'))
            ->get()->getResultArray();

        ProfileModel::setBypassAccountScope(true);
        $agents = (new ProfileModel())->where('account_id', session('account_id'))
            ->whereIn('account_role', ['owner', 'admin', 'agent'])->findAll();
        ProfileModel::setBypassAccountScope(false);

        return view('contacts/edit', [
            'pageTitle'      => 'Edit Contact',
            'contact'        => $contact,
            'allTags'        => (new TagModel())->findAll(),
            'selectedTagIds' => $selectedTagIds,
            'customFields'   => $customFields,
            'agents'         => $agents,
        ]);
    }

    public function update(string $contactId)
    {
        $contactModel = new ContactModel();
        $contact      = $contactModel->find($contactId);

        if (!$contact) {
            return redirect()->to(base_url('contacts'))->with('error', 'Contact not found.');
        }

        $phone           = $this->request->getPost('phone') ?: $contact['phone'];
        $phoneNormalized = PhoneUtils::normalize($phone);

        $contactModel->update($contactId, [
            'phone'             => $phone,
            'phone_normalized'  => $phoneNormalized,
            'name'              => $this->request->getPost('name') ?: null,
            'email'             => $this->request->getPost('email') ?: null,
            'company'           => $this->request->getPost('company') ?: null,
            'channel'           => $this->request->getPost('channel') ?: null,
            'vertical'          => $this->request->getPost('vertical') ?: null,
            'status'            => $this->request->getPost('status') ?: 'New',
            'assigned_agent_id' => $this->request->getPost('assigned_agent_id') ?: null,
            'follow_up_date'    => $this->request->getPost('follow_up_date') ?: null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        // Sync tags — delete all then re-insert
        $ctModel = new ContactTagModel();
        $ctModel->where('contact_id', $contactId)->delete();
        $tagIds = $this->request->getPost('tag_ids') ?? [];
        foreach ($tagIds as $tagId) {
            $ctModel->insert(['contact_id' => $contactId, 'tag_id' => $tagId]);
        }

        // Sync custom fields
        $cfvModel     = new ContactCustomValueModel();
        $customValues = $this->request->getPost('custom_fields') ?? [];
        foreach ($customValues as $fieldId => $value) {
            $existing = $cfvModel->where('contact_id', $contactId)->where('custom_field_id', $fieldId)->first();
            if ($existing) {
                if ($value !== '') {
                    $cfvModel->where('contact_id', $contactId)->where('custom_field_id', $fieldId)->set('value', $value)->update();
                } else {
                    $cfvModel->where('contact_id', $contactId)->where('custom_field_id', $fieldId)->delete();
                }
            } elseif ($value !== '') {
                $cfvModel->insert(['contact_id' => $contactId, 'custom_field_id' => $fieldId, 'value' => $value]);
            }
        }

        return redirect()->to(base_url('contacts/' . $contactId))->with('success', 'Contact updated.');
    }

    public function delete(string $contactId)
    {
        if (!has_min_role('admin')) {
            return redirect()->back()->with('error', 'Permission denied.');
        }

        $contact = (new ContactModel())->find($contactId);
        if (!$contact) {
            return redirect()->to(base_url('contacts'))->with('error', 'Contact not found.');
        }

        $db = \Config\Database::connect();
        // SET NULL on conversations and deals
        $db->table('conversations')->where('contact_id', $contactId)->update(['contact_id' => null]);
        $db->table('deals')->where('contact_id', $contactId)->update(['contact_id' => null]);

        // Delete contact (FK CASCADE handles contact_tags, contact_custom_values, contact_notes)
        (new ContactModel())->delete($contactId);

        return redirect()->to(base_url('contacts'))->with('success', 'Contact deleted.');
    }

    // ---- CSV Import ----

    public function import()
    {
        $customFields = (new CustomFieldModel())->where('account_id', session('account_id'))->findAll();
        return view('contacts/import', [
            'pageTitle'    => 'Import Contacts',
            'customFields' => $customFields,
            'step'         => 1,
        ]);
    }

    public function processImport()
    {
        $file = $this->request->getFile('csv_file');

        if (!$file || !$file->isValid()) {
            return redirect()->to(base_url('contacts/import'))->with('error', 'Please upload a CSV file.');
        }

        if (strtolower($file->getExtension()) !== 'csv') {
            return redirect()->to(base_url('contacts/import'))->with('error', 'Only CSV files are accepted.');
        }

        if ($file->getSizeByUnit('mb') > 10) {
            return redirect()->to(base_url('contacts/import'))->with('error', 'File must be under 10MB.');
        }

        $uploadDir = WRITEPATH . 'uploads/csv-imports/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = generate_uuid() . '.csv';
        $file->move($uploadDir, $filename);
        $fullPath = $uploadDir . $filename;

        // Parse header + preview rows
        $handle  = fopen($fullPath, 'r');
        $headers = fgetcsv($handle);
        $preview = [];
        for ($i = 0; $i < 5 && ($row = fgetcsv($handle)) !== false; $i++) {
            $preview[] = $row;
        }
        fclose($handle);

        session()->set('csv_import_file', $filename);
        session()->set('csv_import_headers', $headers);

        $customFields = (new CustomFieldModel())->where('account_id', session('account_id'))->findAll();

        return view('contacts/import', [
            'pageTitle'    => 'Import Contacts — Map Columns',
            'customFields' => $customFields,
            'step'         => 2,
            'headers'      => $headers,
            'preview'      => $preview,
        ]);
    }

    public function confirmImport()
    {
        $filename = session('csv_import_file');
        $headers  = session('csv_import_headers');

        if (!$filename || !$headers) {
            return redirect()->to(base_url('contacts/import'))->with('error', 'Session expired. Please re-upload.');
        }

        $fullPath = WRITEPATH . 'uploads/csv-imports/' . $filename;
        if (!file_exists($fullPath)) {
            return redirect()->to(base_url('contacts/import'))->with('error', 'Upload file not found.');
        }

        $mapping      = $this->request->getPost('mapping') ?? [];
        $duplicateMode = $this->request->getPost('duplicate_mode') ?? 'update';

        $contactModel = new ContactModel();
        $ctModel      = new ContactTagModel();
        $tagModel     = new TagModel();
        $cfvModel     = new ContactCustomValueModel();

        $created = $updated = $skipped = $errorCount = 0;
        $errors  = [];
        $rowNum  = 1;

        $handle = fopen($fullPath, 'r');
        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $data = [];
            foreach ($headers as $i => $header) {
                $data[$header] = $row[$i] ?? '';
            }

            // Map columns
            $mapped = [];
            foreach ($mapping as $csvCol => $field) {
                if ($field && $field !== 'skip') {
                    $mapped[$field] = $data[$csvCol] ?? '';
                }
            }

            $phone = $mapped['phone'] ?? '';
            if (empty($phone)) {
                $errors[] = "Row {$rowNum}: Phone is required";
                $errorCount++;
                continue;
            }

            $phoneNormalized = PhoneUtils::normalize($phone);

            $existing = $contactModel->where('account_id', session('account_id'))
                ->where('phone_normalized', $phoneNormalized)->first();

            $contactData = [
                'phone'            => $phone,
                'phone_normalized' => $phoneNormalized,
                'name'             => $mapped['name'] ?? null,
                'email'            => $mapped['email'] ?? null,
                'company'          => $mapped['company'] ?? null,
            ];

            if ($existing) {
                if ($duplicateMode === 'skip') {
                    $skipped++;
                    continue;
                }
                $contactModel->update($existing['id'], $contactData);
                $contactId = $existing['id'];
                $updated++;
            } else {
                $contactData['account_id'] = session('account_id');
                $contactData['created_at'] = date('Y-m-d H:i:s');
                $contactId = $contactModel->insert($contactData);
                $created++;
            }

            // Tags — comma-separated
            if (!empty($mapped['tags'])) {
                $tagNames = array_map('trim', explode(',', $mapped['tags']));
                foreach ($tagNames as $tagName) {
                    if (!$tagName) continue;
                    $tag = $tagModel->where('account_id', session('account_id'))->where('name', $tagName)->first();
                    if (!$tag) {
                        $tagId = $tagModel->insert(['account_id' => session('account_id'), 'name' => $tagName, 'color' => '#3B82F6']);
                    } else {
                        $tagId = $tag['id'];
                    }
                    $exists = $ctModel->where('contact_id', $contactId)->where('tag_id', $tagId)->first();
                    if (!$exists) $ctModel->insert(['contact_id' => $contactId, 'tag_id' => $tagId]);
                }
            }
        }

        fclose($handle);
        @unlink($fullPath);
        session()->remove(['csv_import_file', 'csv_import_headers']);

        return view('contacts/import', [
            'pageTitle'  => 'Import Complete',
            'step'       => 4,
            'created'    => $created,
            'updated'    => $updated,
            'skipped'    => $skipped,
            'errorCount' => $errorCount,
            'errors'     => $errors,
        ]);
    }
}
