<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class PipelineStagesController extends BaseController
{
    public function store()
    {
        $pipelineId = $this->request->getPost('pipeline_id');
        $name       = trim($this->request->getPost('name') ?? '');

        if (empty($pipelineId) || empty($name)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Pipeline ID and name are required']);
        }

        $db  = \Config\Database::connect();

        // Verify pipeline belongs to account
        $pipeline = $db->table('pipelines')->where('id', $pipelineId)->where('account_id', session('account_id'))->get()->getRowArray();
        if (!$pipeline) return $this->response->setStatusCode(404)->setJSON(['error' => 'Pipeline not found']);

        $maxPos = $db->table('pipeline_stages')->selectMax('position')->where('pipeline_id', $pipelineId)->get()->getRow()->position ?? -1;

        $stageId = generate_uuid();
        $color   = $this->request->getPost('color') ?? '#3B82F6';

        $db->table('pipeline_stages')->insert([
            'id'          => $stageId,
            'pipeline_id' => $pipelineId,
            'name'        => $name,
            'color'       => $color,
            'position'    => (int)$maxPos + 1,
        ]);

        $stage = $db->table('pipeline_stages')->where('id', $stageId)->get()->getRowArray();
        return $this->response->setStatusCode(201)->setJSON($stage);
    }

    public function update(string $stageId)
    {
        $db    = \Config\Database::connect();
        $stage = $db->table('pipeline_stages ps')
            ->join('pipelines pl', 'pl.id = ps.pipeline_id')
            ->where('ps.id', $stageId)
            ->where('pl.account_id', session('account_id'))
            ->get()->getRowArray();

        if (!$stage) return $this->response->setStatusCode(404)->setJSON(['error' => 'Stage not found']);

        $update = [];
        if ($name = trim($this->request->getPost('name') ?? '')) $update['name']  = $name;
        if ($color = $this->request->getPost('color'))             $update['color'] = $color;

        $db->table('pipeline_stages')->where('id', $stageId)->update($update);
        return $this->response->setJSON($db->table('pipeline_stages')->where('id', $stageId)->get()->getRowArray());
    }

    public function reorder()
    {
        $stageIds = $this->request->getPost('stage_ids') ?? [];
        if (empty($stageIds)) return $this->response->setStatusCode(400)->setJSON(['error' => 'Stage IDs required']);

        $db = \Config\Database::connect();
        foreach ($stageIds as $position => $stageId) {
            $db->table('pipeline_stages')->where('id', $stageId)->update(['position' => $position]);
        }

        return $this->response->setJSON(['success' => true]);
    }

    public function delete(string $stageId)
    {
        $db = \Config\Database::connect();

        $dealCount = $db->table('deals')->where('stage_id', $stageId)->where('account_id', session('account_id'))->countAllResults();
        if ($dealCount > 0) {
            return $this->response->setStatusCode(400)->setJSON(['error' => "Move {$dealCount} deals to another stage first"]);
        }

        $db->table('pipeline_stages')->where('id', $stageId)->delete();
        return $this->response->setJSON(['success' => true]);
    }
}
