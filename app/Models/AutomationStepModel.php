<?php

namespace App\Models;

class AutomationStepModel extends BaseModel
{
    protected $table         = 'automation_steps';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'automation_id', 'parent_step_id', 'branch', 'step_type', 'step_config', 'position'];
}
