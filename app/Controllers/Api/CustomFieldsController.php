<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CustomFieldModel;
use App\Models\ContactCustomValueModel;

class CustomFieldsController extends BaseController
{
    private const VALID_TYPES = ['text', 'number', 'date', 'dropdown'];

    public function index()
    {
        $fields = (new CustomFieldModel())->where('account_id', session('account_id'))->findAll();
        return $this->response->setJSON($fields);
    }

    public function store()
    {
        $name      = trim($this->request->getPost('field_name') ?? '');
        $type      = $this->request->getPost('field_type') ?? 'text';
        $optionRaw = $this->request->getPost('field_options') ?? '';

        if (empty($name)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Field name is required']);
        }

        if (!in_array($type, self::VALID_TYPES)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid field type']);
        }

        $fieldOptions = null;
        if ($type === 'dropdown') {
            $opts = array_filter(array_map('trim', explode(',', $optionRaw)));
            if (empty($opts)) {
                return $this->response->setStatusCode(400)->setJSON(['error' => 'Dropdown must have at least one option']);
            }
            $fieldOptions = json_encode(array_values($opts));
        }

        $model = new CustomFieldModel();
        $id    = $model->insert([
            'account_id'    => session('account_id'),
            'field_name'    => $name,
            'field_type'    => $type,
            'field_options' => $fieldOptions,
        ]);

        return $this->response->setStatusCode(201)->setJSON($model->find($id));
    }

    public function update(string $fieldId)
    {
        $model = new CustomFieldModel();
        $field = $model->where('account_id', session('account_id'))->find($fieldId);

        if (!$field) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Field not found']);
        }

        $name      = trim($this->request->getPost('field_name') ?? $field['field_name']);
        $optionRaw = $this->request->getPost('field_options') ?? '';

        $update = ['field_name' => $name];

        if ($field['field_type'] === 'dropdown' && $optionRaw) {
            $opts = array_filter(array_map('trim', explode(',', $optionRaw)));
            $update['field_options'] = json_encode(array_values($opts));
        }

        $model->update($fieldId, $update);

        return $this->response->setJSON($model->find($fieldId));
    }

    public function delete(string $fieldId)
    {
        $model = new CustomFieldModel();
        $field = $model->where('account_id', session('account_id'))->find($fieldId);

        if (!$field) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Field not found']);
        }

        (new ContactCustomValueModel())->where('custom_field_id', $fieldId)->delete();
        $model->delete($fieldId);

        return $this->response->setJSON(['success' => true]);
    }
}
