<?php

namespace App\Models;

class FlowRunEventModel extends BaseModel
{
    protected $table         = 'flow_run_events';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'flow_run_id', 'node_key', 'event_type', 'event_data', 'created_at'];
}
