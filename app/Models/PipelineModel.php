<?php

namespace App\Models;

class PipelineModel extends BaseModel
{
    protected $table         = 'pipelines';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'name'];
}
