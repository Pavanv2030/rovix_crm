<?php

namespace App\Models;

class ContactTagModel extends BaseModel
{
    protected $table         = 'contact_tags';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'contact_id', 'tag_id'];
}
