<?php

namespace App\Models;

class TagModel extends BaseModel
{
    protected $table         = 'tags';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'account_id', 'name', 'color'];
}
