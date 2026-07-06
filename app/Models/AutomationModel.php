<?php

namespace App\Models;

class AutomationModel extends BaseModel
{
    protected $table         = 'automations';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'user_id', 'name', 'trigger_type', 'trigger_config', 'is_active', 'execution_count', 'last_executed_at'];
}
