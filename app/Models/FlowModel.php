<?php

namespace App\Models;

class FlowModel extends BaseModel
{
    protected $table         = 'flows';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'name', 'is_active', 'trigger_keywords', 'execution_count'];
}
