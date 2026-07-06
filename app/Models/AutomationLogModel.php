<?php

namespace App\Models;

class AutomationLogModel extends BaseModel
{
    protected $table         = 'automation_logs';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'automation_id', 'contact_id', 'trigger_event', 'steps_executed', 'status', 'error_message', 'created_at'];
}
