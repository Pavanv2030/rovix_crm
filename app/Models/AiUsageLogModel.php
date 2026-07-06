<?php

namespace App\Models;

class AiUsageLogModel extends BaseModel
{
    protected $table         = 'ai_usage_log';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'account_id', 'feature', 'model',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'cost_estimate', 'created_at',
    ];
}
