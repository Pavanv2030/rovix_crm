<?php

namespace App\Models;

class ContactModel extends BaseModel
{
    protected $table         = 'contacts';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'phone', 'phone_normalized', 'name', 'email', 'company', 'avatar_url', 'channel', 'vertical', 'status', 'assigned_agent_id', 'follow_up_date', 'is_phone_verified'];
}
