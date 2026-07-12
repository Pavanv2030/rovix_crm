<?php

namespace App\Commands;

use App\Models\FlowRunModel;
use App\Models\BaseModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CleanupStaleFlows extends BaseCommand
{
    protected $group       = 'Flows';
    protected $name        = 'flows:cleanup';
    protected $description = 'Mark flow runs active for over 24 h as timed_out';

    public function run(array $params)
    {
        BaseModel::runUnscoped(function () {
            $staleThreshold = date('Y-m-d H:i:s', time() - 86400);

            $model     = new FlowRunModel();
            $staleRuns = $model
                ->where('status', 'active')
                ->where('updated_at <', $staleThreshold)
                ->findAll();

            if (empty($staleRuns)) {
                CLI::write('No stale flow runs found.', 'yellow');
                return;
            }

            CLI::write('Timing out ' . count($staleRuns) . ' stale flow run(s)…', 'blue');

            foreach ($staleRuns as $run) {
                $model->update($run['id'], [
                    'status'     => 'timed_out',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            CLI::write('Done.', 'green');
        });
    }
}
