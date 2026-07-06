<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\TagModel;
use App\Models\ContactTagModel;

class TagsController extends BaseController
{
    public function index()
    {
        $db   = \Config\Database::connect();
        $tags = $db->table('tags t')
            ->select('t.*, COUNT(ct.id) as contact_count')
            ->join('contact_tags ct', 'ct.tag_id = t.id', 'left')
            ->where('t.account_id', session('account_id'))
            ->groupBy('t.id')
            ->get()->getResultArray();

        return $this->response->setJSON($tags);
    }

    public function store()
    {
        $name  = trim($this->request->getPost('name') ?? '');
        $color = $this->request->getPost('color') ?? '#3B82F6';

        if (empty($name)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Tag name is required']);
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#3B82F6';
        }

        $tagModel = new TagModel();
        $existing = $tagModel->where('account_id', session('account_id'))->where('name', $name)->first();
        if ($existing) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Tag name already exists']);
        }

        $id  = $tagModel->insert(['account_id' => session('account_id'), 'name' => $name, 'color' => $color]);
        $tag = $tagModel->find($id);

        return $this->response->setStatusCode(201)->setJSON($tag);
    }

    public function update(string $tagId)
    {
        $tagModel = new TagModel();
        $tag      = $tagModel->where('account_id', session('account_id'))->find($tagId);

        if (!$tag) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Tag not found']);
        }

        $name  = trim($this->request->getPost('name') ?? $tag['name']);
        $color = $this->request->getPost('color') ?? $tag['color'];

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = $tag['color'];
        }

        // Unique check (exclude self)
        $existing = $tagModel->where('account_id', session('account_id'))->where('name', $name)->where('id !=', $tagId)->first();
        if ($existing) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Tag name already exists']);
        }

        $tagModel->update($tagId, ['name' => $name, 'color' => $color]);

        return $this->response->setJSON($tagModel->find($tagId));
    }

    public function delete(string $tagId)
    {
        $tagModel = new TagModel();
        $tag      = $tagModel->where('account_id', session('account_id'))->find($tagId);

        if (!$tag) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Tag not found']);
        }

        // Delete contact_tags first
        (new ContactTagModel())->where('tag_id', $tagId)->delete();
        $tagModel->delete($tagId);

        return $this->response->setJSON(['success' => true]);
    }
}
