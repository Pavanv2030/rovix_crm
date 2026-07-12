<?php

namespace App\Commands;

use App\Libraries\JobDispatcher;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RunScheduled extends BaseCommand
{
    protected $group       = 'Cron';
    protected $name        = 'run:scheduled';
    protected $description = 'Run all scheduled tasks (called by cron every minute)';

    public function run(array $params)
    {
        CLI::write('Running scheduled tasks...', 'yellow');

        command('queue:process');
        command('appointments:reminders');

        $hour = (int) date('H');

        // Multi-tenant: each account picks its own send time in
        // Settings → Notifications, so this checks every account's
        // configured time against the current minute instead of a single
        // hardcoded hour for everyone.
        \App\Models\BaseModel::runUnscoped(function () {
            $currentTime = date('H:i');
            $accounts    = (new \App\Models\AccountModel())->findAll();

            foreach ($accounts as $acct) {
                $prefs = json_decode($acct['notification_preferences'] ?? '{}', true) ?? [];
                $founderNumber = trim($prefs['daily_report_founder_number'] ?? '');
                $hrNumber      = trim($prefs['daily_report_hr_number'] ?? '');
                $reportTime    = $prefs['daily_report_time'] ?? '08:00';

                if (($founderNumber || $hrNumber) && substr($reportTime, 0, 5) === $currentTime) {
                    CLI::write('Dispatching daily report for account ' . $acct['id'], 'blue');
                    (new JobDispatcher())->dispatch('send_daily_report', ['account_id' => $acct['id']], null, 10);
                }
            }
        });

        if ($hour === 2) {
            CLI::write('Running media cleanup...', 'blue');
            command('media:cleanup');
        }

        if ($hour === 3) {
            CLI::write('Cleaning up stale flow runs...', 'blue');
            command('flows:cleanup');
        }

        if ($hour === 4) {
            CLI::write('Cleaning up old webhook logs...', 'blue');
            command('webhooks:cleanup');
        }

        // Dispatch any broadcasts whose scheduled_at has passed
        \App\Models\BaseModel::runUnscoped(function () {
            $broadcastModel = new \App\Models\BroadcastModel();
            $due = $broadcastModel
                ->where('status', 'scheduled')
                ->where('scheduled_at <=', date('Y-m-d H:i:s'))
                ->findAll();

            foreach ($due as $broadcast) {
                CLI::write('Dispatching scheduled broadcast: ' . $broadcast['name'], 'blue');
                try {
                    (new \App\Libraries\BroadcastProcessor())->prepare($broadcast['id']);
                } catch (\Exception $e) {
                    CLI::write('  Failed: ' . $e->getMessage(), 'red');
                }
            }
        });

        // Fire time_based automations whose schedule matches current time
        $automationModel = new \App\Models\AutomationModel();
        $timeAutomations = $automationModel
            ->where('trigger_type', 'time_based')
            ->where('is_active', 1)
            ->findAll();

        $currentDay  = strtolower(date('l')); // e.g. "monday"
        $currentTime = date('H:i');

        foreach ($timeAutomations as $automation) {
            $tc = json_decode($automation['trigger_config'] ?? '{}', true) ?? [];
            $schedule = $tc['schedule'] ?? 'daily';
            $tcTime   = $tc['time']     ?? '09:00';
            $tcDay    = $tc['day']      ?? 'monday';

            $timeMatches = substr($tcTime, 0, 5) === $currentTime;
            $dayMatches  = $schedule === 'daily' || ($schedule === 'weekly' && $tcDay === $currentDay);

            if ($timeMatches && $dayMatches) {
                CLI::write('Firing time-based automation: ' . $automation['name'], 'blue');
                $contacts = (new \App\Models\ContactModel())->where('account_id', $automation['account_id'])->findAll();
                $engine   = new \App\Libraries\AutomationEngine();
                foreach ($contacts as $contact) {
                    try {
                        $engine->execute($automation, $contact['id'], ['trigger' => 'time_based']);
                    } catch (\Exception $e) {
                        CLI::write('  Failed for contact ' . $contact['id'] . ': ' . $e->getMessage(), 'red');
                    }
                }
            }
        }

        CLI::write('Scheduled tasks complete', 'green');
    }
}
