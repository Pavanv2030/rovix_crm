<?php

namespace App\Models;

class ContactCustomValueModel extends BaseModel
{
    protected $table         = 'contact_custom_values';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'contact_id', 'custom_field_id', 'value'];
}
