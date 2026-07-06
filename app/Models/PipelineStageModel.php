<?php

namespace App\Models;

class PipelineStageModel extends BaseModel
{
    protected $table         = 'pipeline_stages';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'pipeline_id', 'name', 'position', 'color'];
}
