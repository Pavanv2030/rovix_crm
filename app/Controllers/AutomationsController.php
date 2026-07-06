<?php

namespace App\Controllers;

use App\Models\AutomationModel;
use App\Models\AutomationStepModel;
use App\Models\AutomationLogModel;
use App\Models\MessageTemplateModel;
use App\Models\TagModel;
use App\Models\ProfileModel;
use App\Models\PipelineModel;
use App\Models\PipelineStageModel;
use App\Models\AppointmentTypeModel;

class AutomationsController extends BaseController
{
    public function index()
    {
        $model       = new AutomationModel();
        $automations = $model->orderBy('created_at', 'DESC')->findAll();

        $stepModel = new AutomationStepModel();
        foreach ($automations as &$a) {
            $a['step_count'] = $stepModel->where('automation_id', $a['id'])->countAllResults();
        }
        unset($a);

        return view('automations/index', [
            'pageTitle'    => 'Automations',
            'automations'  => $automations,
        ]);
    }

    public function create()
    {
        return view('automations/create', [
            'pageTitle'        => 'New Automation',
            'templates'        => (new MessageTemplateModel())->where('status', 'approved')->orderBy('name')->findAll(),
            'tags'             => (new TagModel())->orderBy('name')->findAll(),
            'team'             => (new ProfileModel())->orderBy('full_name')->findAll(),
            'stages'           => $this->allStages(),
            'appointmentTypes' => (new AppointmentTypeModel())->where('active', 1)->orderBy('name')->findAll(),
        ]);
    }

    public function store()
    {
        $name        = trim($this->request->getPost('name') ?? '');
        $triggerType = $this->request->getPost('trigger_type');
        $stepsJson   = $this->request->getPost('steps_json') ?? '[]';

        if (empty($name)) {
            return redirect()->back()->withInput()->with('error', 'Name is required.');
        }
        if (empty($triggerType)) {
            return redirect()->back()->withInput()->with('error', 'Please select a trigger.');
        }

        $triggerConfig = $this->buildTriggerConfig($triggerType);

        $model = new AutomationModel();
        $id = $model->insert([
            'account_id'      => session('account_id'),
            'user_id'         => session('user_id'),
            'name'            => $name,
            'trigger_type'    => $triggerType,
            'trigger_config'  => json_encode($triggerConfig),
            'is_active'       => 1,
            'execution_count' => 0,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->saveSteps($id, $stepsJson);

        return redirect()->to(base_url('automations/' . $id))->with('success', 'Automation created.');
    }

    public function view(string $id)
    {
        $automation = (new AutomationModel())->find($id);
        if (!$automation) return redirect()->to(base_url('automations'))->with('error', 'Not found.');

        $steps = (new AutomationStepModel())
            ->where('automation_id', $id)
            ->orderBy('position', 'ASC')
            ->findAll();

        $page    = (int)($this->request->getGet('page') ?? 1);
        $perPage = 20;
        $logs    = (new AutomationLogModel())
            ->where('automation_id', $id)
            ->orderBy('created_at', 'DESC')
            ->findAll($perPage, ($page - 1) * $perPage);

        $totalLogs = (new AutomationLogModel())->where('automation_id', $id)->countAllResults();

        $contactModel = new \App\Models\ContactModel();
        foreach ($logs as &$log) {
            if ($log['contact_id']) {
                $c = $contactModel->find($log['contact_id']);
                $log['contact_name']  = $c['name']  ?? 'Unknown';
                $log['contact_phone'] = $c['phone'] ?? '';
            } else {
                $log['contact_name']  = '—';
                $log['contact_phone'] = '';
            }
        }
        unset($log);

        return view('automations/view', [
            'pageTitle'  => $automation['name'],
            'automation' => $automation,
            'steps'      => $steps,
            'logs'       => $logs,
            'totalLogs'  => $totalLogs,
            'page'       => $page,
            'perPage'    => $perPage,
            'tags'       => $this->tagsMap(),
        ]);
    }

    public function edit(string $id)
    {
        $automation = (new AutomationModel())->find($id);
        if (!$automation) return redirect()->to(base_url('automations'))->with('error', 'Not found.');

        $steps = (new AutomationStepModel())
            ->where('automation_id', $id)
            ->orderBy('position', 'ASC')
            ->findAll();

        // Build flat step list for the form (type + config only)
        $stepsForForm = array_map(fn($s) => [
            'type'   => $s['step_type'],
            'config' => json_decode($s['step_config'] ?? '{}', true) ?? [],
        ], $steps);

        $triggerConfig = json_decode($automation['trigger_config'] ?? '{}', true) ?? [];

        return view('automations/create', [
            'pageTitle'        => 'Edit Automation',
            'automation'       => $automation,
            'triggerConfig'    => $triggerConfig,
            'stepsForForm'     => $stepsForForm,
            'templates'        => (new MessageTemplateModel())->where('status', 'approved')->orderBy('name')->findAll(),
            'tags'             => (new TagModel())->orderBy('name')->findAll(),
            'team'             => (new ProfileModel())->orderBy('full_name')->findAll(),
            'stages'           => $this->allStages(),
            'appointmentTypes' => (new AppointmentTypeModel())->where('active', 1)->orderBy('name')->findAll(),
        ]);
    }

    public function update(string $id)
    {
        $automation = (new AutomationModel())->find($id);
        if (!$automation) return redirect()->to(base_url('automations'))->with('error', 'Not found.');

        $name        = trim($this->request->getPost('name') ?? '');
        $triggerType = $this->request->getPost('trigger_type');
        $stepsJson   = $this->request->getPost('steps_json') ?? '[]';

        if (empty($name) || empty($triggerType)) {
            return redirect()->back()->withInput()->with('error', 'Name and trigger are required.');
        }

        $triggerConfig = $this->buildTriggerConfig($triggerType);

        (new AutomationModel())->update($id, [
            'name'           => $name,
            'trigger_type'   => $triggerType,
            'trigger_config' => json_encode($triggerConfig),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Replace steps
        (new AutomationStepModel())->where('automation_id', $id)->delete();
        $this->saveSteps($id, $stepsJson);

        return redirect()->to(base_url('automations/' . $id))->with('success', 'Automation updated.');
    }

    public function toggle(string $id)
    {
        $model      = new AutomationModel();
        $automation = $model->find($id);
        if (!$automation) return redirect()->back()->with('error', 'Not found.');

        $newState = $automation['is_active'] ? 0 : 1;
        $model->update($id, ['is_active' => $newState, 'updated_at' => date('Y-m-d H:i:s')]);

        $label = $newState ? 'activated' : 'paused';
        return redirect()->to(base_url('automations/' . $id))->with('success', "Automation {$label}.");
    }

    public function delete(string $id)
    {
        $automation = (new AutomationModel())->find($id);
        if (!$automation) return redirect()->to(base_url('automations'))->with('error', 'Not found.');

        (new AutomationStepModel())->where('automation_id', $id)->delete();
        (new AutomationLogModel())->where('automation_id', $id)->delete();
        (new AutomationModel())->delete($id);

        return redirect()->to(base_url('automations'))->with('success', 'Automation deleted.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildTriggerConfig(string $triggerType): array
    {
        return match ($triggerType) {
            'keyword_match' => [
                'keywords'  => $this->request->getPost('tc_keywords') ?? '',
                'match_all' => (bool)$this->request->getPost('tc_match_all'),
            ],
            'tag_added' => [
                'tag_id' => $this->request->getPost('tc_tag_id') ?? '',
            ],
            'time_based' => [
                'schedule' => $this->request->getPost('tc_schedule') ?? 'daily',
                'time'     => $this->request->getPost('tc_time')     ?? '09:00',
                'day'      => $this->request->getPost('tc_day')      ?? 'monday',
            ],
            default => [],
        };
    }

    private function saveSteps(string $automationId, string $stepsJson): void
    {
        $stepsData = json_decode($stepsJson, true);
        if (!is_array($stepsData)) return;

        $stepModel  = new AutomationStepModel();
        $prevStepId = null;

        foreach ($stepsData as $i => $stepData) {
            $type   = $stepData['type']   ?? null;
            $config = $stepData['config'] ?? [];

            if (!$type) continue;

            $newId = $stepModel->insert([
                'automation_id'  => $automationId,
                'parent_step_id' => $prevStepId,
                'branch'         => null,
                'step_type'      => $type,
                'step_config'    => json_encode($config),
                'position'       => $i,
            ]);

            $prevStepId = $newId;
        }
    }

    private function allStages(): array
    {
        $pipelines = (new PipelineModel())->orderBy('name')->findAll();
        $result    = [];
        $stageModel = new PipelineStageModel();
        foreach ($pipelines as $p) {
            $stages = $stageModel->where('pipeline_id', $p['id'])->orderBy('position')->findAll();
            foreach ($stages as $s) {
                $result[] = ['id' => $s['id'], 'name' => $p['name'] . ' → ' . $s['name']];
            }
        }
        return $result;
    }

    private function tagsMap(): array
    {
        $map = [];
        foreach ((new TagModel())->findAll() as $tag) {
            $map[$tag['id']] = $tag['name'];
        }
        return $map;
    }
}
