<?php

namespace App\Models;

class JobQueueModel extends BaseModel
{
    protected $table            = 'job_queue';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
    protected $allowedFields    = ['job_type', 'payload', 'status', 'priority', 'locked_until', 'run_after', 'attempts', 'max_retries', 'error', 'failed_attempts_log'];

    private function hasAccountId(): bool { return false; }
}
