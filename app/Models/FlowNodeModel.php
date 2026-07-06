<?php

namespace App\Models;

class FlowNodeModel extends BaseModel
{
    protected $table         = 'flow_nodes';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'flow_id', 'node_key', 'node_type', 'config', 'position_x', 'position_y'];
}
