<?php

namespace App\Models;

class FlowRunModel extends BaseModel
{
    protected $table         = 'flow_runs';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'flow_id', 'contact_id', 'conversation_id', 'status', 'current_node_key', 'vars', 'meta_message_id', 'started_at', 'updated_at'];
}
