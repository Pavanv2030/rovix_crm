<?php

namespace App\Models;

class DealModel extends BaseModel
{
    protected $table         = 'deals';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'pipeline_id', 'stage_id', 'contact_id', 'conversation_id', 'title', 'value', 'currency', 'status', 'expected_close_date', 'assigned_agent_id', 'notes'];
}
