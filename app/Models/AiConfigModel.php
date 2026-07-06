<?php

namespace App\Models;

class AiConfigModel extends BaseModel
{
    protected $table         = 'ai_configs';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'provider', 'api_key', 'model', 'created_at', 'updated_at'];
}
