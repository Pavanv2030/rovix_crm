<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CleanupWebhookLogs extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'webhooks:cleanup';
    protected $description = 'Delete webhook logs older than 30 days';

    public function run(array $params)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));

        $db      = \Config\Database::connect();
        $builder = $db->table('webhook_logs')->where('created_at <', $cutoff);
        $count   = $builder->countAllResults(false);
        $builder->delete();

        CLI::write("Deleted {$count} webhook log(s) older than 30 days.", 'green');
    }
}
