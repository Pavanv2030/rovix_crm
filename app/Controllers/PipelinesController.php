<?php

namespace App\Controllers;

use App\Models\PipelineModel;
use App\Models\PipelineStageModel;
use App\Models\DealModel;
use App\Models\ProfileModel;

class PipelinesController extends BaseController
{
    private const DEFAULT_STAGES = [
        ['name' => 'New Lead',    'color' => '#3B82F6'],
        ['name' => 'Qualified',   'color' => '#10B981'],
        ['name' => 'Proposal',    'color' => '#F59E0B'],
        ['name' => 'Negotiation', 'color' => '#EF4444'],
        ['name' => 'Closed Won',  'color' => '#8B5CF6'],
    ];

    public function index()
    {
        $db        = \Config\Database::connect();
        $pipelines = (new PipelineModel())->findAll();

        foreach ($pipelines as &$pipeline) {
            $pipeline['stages'] = $db->table('pipeline_stages')
                ->where('pipeline_id', $pipeline['id'])
                ->orderBy('position', 'ASC')
                ->get()->getResultArray();

            $pipeline['deal_count'] = $db->table('deals')
                ->where('pipeline_id', $pipeline['id'])
                ->where('account_id', session('account_id'))
                ->countAllResults();

            $pipeline['deal_value'] = $db->table('deals')
                ->selectSum('value')
                ->where('pipeline_id', $pipeline['id'])
                ->where('account_id', session('account_id'))
                ->get()->getRow()->value ?? 0;
        }

        return view('pipelines/index', ['pageTitle' => 'Pipelines', 'pipelines' => $pipelines]);
    }

    public function create()
    {
        return view('pipelines/create', [
            'pageTitle'     => 'New Pipeline',
            'defaultStages' => self::DEFAULT_STAGES,
        ]);
    }

    public function store()
    {
        $name = trim($this->request->getPost('name') ?? '');
        if (empty($name)) {
            return redirect()->back()->withInput()->with('error', 'Pipeline name is required.');
        }

        $pipelineModel = new PipelineModel();
        $pipelineId    = $pipelineModel->insert(['name' => $name, 'account_id' => session('account_id'), 'created_at' => date('Y-m-d H:i:s')]);

        $db         = \Config\Database::connect();
        $stageNames = $this->request->getPost('stage_names') ?? array_column(self::DEFAULT_STAGES, 'name');
        $stageColors = $this->request->getPost('stage_colors') ?? array_column(self::DEFAULT_STAGES, 'color');

        foreach ($stageNames as $i => $stageName) {
            if (empty(trim($stageName))) continue;
            $db->table('pipeline_stages')->insert([
                'id'          => generate_uuid(),
                'pipeline_id' => $pipelineId,
                'name'        => trim($stageName),
                'color'       => $stageColors[$i] ?? '#3B82F6',
                'position'    => $i,
            ]);
        }

        return redirect()->to(base_url('pipelines/' . $pipelineId . '/board'))->with('success', 'Pipeline created!');
    }

    public function edit(string $pipelineId)
    {
        $pipeline = (new PipelineModel())->find($pipelineId);
        if (!$pipeline) return redirect()->to(base_url('pipelines'))->with('error', 'Pipeline not found.');

        $stages = \Config\Database::connect()->table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return view('pipelines/edit', ['pageTitle' => 'Edit Pipeline', 'pipeline' => $pipeline, 'stages' => $stages]);
    }

    public function update(string $pipelineId)
    {
        $pipeline = (new PipelineModel())->find($pipelineId);
        if (!$pipeline) return redirect()->to(base_url('pipelines'))->with('error', 'Pipeline not found.');

        (new PipelineModel())->update($pipelineId, ['name' => trim($this->request->getPost('name'))]);

        $db          = \Config\Database::connect();
        $stageIds    = $this->request->getPost('stage_ids') ?? [];
        $stageNames  = $this->request->getPost('stage_names') ?? [];
        $stageColors = $this->request->getPost('stage_colors') ?? [];
        $newNames    = $this->request->getPost('new_stage_names') ?? [];
        $newColors   = $this->request->getPost('new_stage_colors') ?? [];

        // Update existing stages — scoped to this pipeline so a stage_id
        // from a different pipeline/account can never be edited via this form.
        foreach ($stageIds as $i => $sid) {
            $db->table('pipeline_stages')->where('id', $sid)->where('pipeline_id', $pipelineId)->update([
                'name'     => trim($stageNames[$i] ?? ''),
                'color'    => $stageColors[$i] ?? '#3B82F6',
                'position' => $i,
            ]);
        }

        // Add new stages
        $offset = count($stageIds);
        foreach ($newNames as $j => $newName) {
            if (empty(trim($newName))) continue;
            $db->table('pipeline_stages')->insert([
                'id'          => generate_uuid(),
                'pipeline_id' => $pipelineId,
                'name'        => trim($newName),
                'color'       => $newColors[$j] ?? '#3B82F6',
                'position'    => $offset + $j,
            ]);
        }

        return redirect()->to(base_url('pipelines/' . $pipelineId . '/board'))->with('success', 'Pipeline updated.');
    }

    public function delete(string $pipelineId)
    {
        if (!has_min_role('admin')) return redirect()->back()->with('error', 'Permission denied.');

        $pipeline = (new PipelineModel())->find($pipelineId);
        if (!$pipeline) return redirect()->to(base_url('pipelines'))->with('error', 'Pipeline not found.');

        $db = \Config\Database::connect();
        $db->table('deals')->where('pipeline_id', $pipelineId)->update(['pipeline_id' => null, 'stage_id' => null]);
        (new PipelineModel())->delete($pipelineId);

        return redirect()->to(base_url('pipelines'))->with('success', 'Pipeline deleted.');
    }

    public function board(string $pipelineId)
    {
        $pipeline = (new PipelineModel())->find($pipelineId);
        if (!$pipeline) return redirect()->to(base_url('pipelines'))->with('error', 'Pipeline not found.');

        $db     = \Config\Database::connect();
        $stages = $db->table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        // Load deals with contact + agent info
        $allDeals = $db->table('deals d')
            ->select('d.*, c.name as contact_name, c.phone as contact_phone, p.full_name as agent_name')
            ->join('contacts c', 'c.id = d.contact_id', 'left')
            ->join('profiles p', 'p.user_id = d.assigned_agent_id', 'left')
            ->where('d.pipeline_id', $pipelineId)
            ->where('d.account_id', session('account_id'))
            ->where('d.status', 'open')
            ->orderBy('d.created_at', 'ASC')
            ->get()->getResultArray();

        // Group by stage
        $dealsByStage = [];
        foreach ($stages as $stage) $dealsByStage[$stage['id']] = [];
        foreach ($allDeals as $deal) {
            if (isset($dealsByStage[$deal['stage_id']])) {
                $dealsByStage[$deal['stage_id']][] = $deal;
            }
        }

        return view('pipelines/board', [
            'pageTitle'    => $pipeline['name'],
            'pipeline'     => $pipeline,
            'stages'       => $stages,
            'dealsByStage' => $dealsByStage,
        ]);
    }
}
