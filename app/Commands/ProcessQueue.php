<?php

namespace App\Commands;

use App\Models\BaseModel;
use App\Models\JobQueueModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ProcessQueue extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'queue:process';
    protected $description = 'Process pending background jobs';

    public function run(array $params)
    {
        BaseModel::setBypassAccountScope(true);

        $model = new JobQueueModel();

        // Unlock stale processing jobs
        $model->where('status', 'processing')
            ->where('locked_until <', date('Y-m-d H:i:s'))
            ->set(['status' => 'pending', 'locked_until' => null])
            ->update();

        $jobs = $model->where('status', 'pending')
            ->groupStart()
                ->where('run_after IS NULL')
                ->orWhere('run_after <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->findAll(50);

        if (empty($jobs)) {
            CLI::write('No pending jobs', 'yellow');
            return;
        }

        CLI::write('Processing ' . count($jobs) . ' jobs...', 'green');

        foreach ($jobs as $job) {
            $this->processJob($job, $model);
        }

        CLI::write('Done', 'green');
    }

    private function processJob(array $job, JobQueueModel $model)
    {
        $model->update($job['id'], [
            'status'       => 'processing',
            'locked_until' => date('Y-m-d H:i:s', time() + 300),
            'attempts'     => $job['attempts'] + 1,
        ]);

        $payload = json_decode($job['payload'], true);

        try {
            CLI::write("Processing job #{$job['id']} ({$job['job_type']})", 'blue');

            switch ($job['job_type']) {
                case 'send_message':
                    CLI::write('  Sending message to ' . ($payload['to'] ?? '?'), 'cyan');
                    break;
                case 'run_automation':
                    $engine = new \App\Libraries\AutomationEngine();
                    $engine->fire(
                        $payload['trigger_type'] ?? 'new_message_received',
                        $payload,
                        $payload['contact_id'],
                        $payload['account_id']
                    );
                    CLI::write('  Running automation for contact ' . ($payload['contact_id'] ?? '?'), 'cyan');
                    break;
                case 'send_whatsapp_message':
                    (new \App\Libraries\MessageSender())->sendText($payload);
                    CLI::write('  Sent message for conversation ' . ($payload['conversation_id'] ?? '?'), 'cyan');
                    break;
                case 'send_whatsapp_template':
                    (new \App\Libraries\MessageSender())->sendTemplate($payload);
                    CLI::write('  Sent template for conversation ' . ($payload['conversation_id'] ?? '?'), 'cyan');
                    break;
                case 'check_flow':
                    $engine = new \App\Libraries\FlowEngine();
                    $engine->dispatchInbound($payload);
                    CLI::write('  Flow processed for contact ' . ($payload['contact_id'] ?? '?'), 'cyan');
                    break;
                case 'send_broadcast_batch':
                    $processor = new \App\Libraries\BroadcastProcessor();
                    $result    = $processor->processBatch(
                        $payload['broadcast_id'],
                        $payload['recipient_ids']
                    );
                    CLI::write("  Broadcast batch done — sent: {$result['sent']}, failed: {$result['failed']}", 'cyan');
                    break;
                case 'resume_automation':
                    $engine = new \App\Libraries\AutomationEngine();
                    $engine->resumeFrom(
                        $payload['automation_id'],
                        $payload['contact_id'],
                        $payload['from_step_id']
                    );
                    CLI::write('  Automation resumed for contact ' . ($payload['contact_id'] ?? '?'), 'cyan');
                    break;
                case 'send_daily_report':
                    (new \App\Libraries\DailyReportSender())->send($payload['account_id']);
                    CLI::write('  Sent daily report for account ' . ($payload['account_id'] ?? '?'), 'cyan');
                    break;
                default:
                    throw new \Exception('Unknown job type: ' . $job['job_type']);
            }

            $model->update($job['id'], ['status' => 'done', 'locked_until' => null]);
            CLI::write("✓ Job #{$job['id']} completed", 'green');

        } catch (\Exception $e) {
            CLI::write("✗ Job #{$job['id']} failed: " . $e->getMessage(), 'red');

            $failedLog   = json_decode($job['failed_attempts_log'] ?? '[]', true);
            $failedLog[] = ['attempt' => $job['attempts'] + 1, 'error' => $e->getMessage(), 'timestamp' => date('Y-m-d H:i:s')];

            if ($job['attempts'] + 1 >= $job['max_retries']) {
                $model->update($job['id'], [
                    'status'              => 'failed',
                    'error'               => $e->getMessage(),
                    'failed_attempts_log' => json_encode($failedLog),
                    'locked_until'        => null,
                ]);
            } else {
                $model->update($job['id'], [
                    'status'              => 'pending',
                    'run_after'           => date('Y-m-d H:i:s', time() + (60 * (2 ** $job['attempts']))),
                    'failed_attempts_log' => json_encode($failedLog),
                    'locked_until'        => null,
                ]);
            }
        }
    }
}
