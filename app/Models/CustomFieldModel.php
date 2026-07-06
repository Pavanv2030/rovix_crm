<?php

namespace App\Models;

class CustomFieldModel extends BaseModel
{
    protected $table         = 'custom_fields';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'account_id', 'field_name', 'field_type', 'field_options'];
}
