<?php

namespace App\Models;

class ConversationModel extends BaseModel
{
    protected $table         = 'conversations';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'contact_id', 'status', 'lead_status_id', 'assigned_agent_id', 'unread_count', 'last_message_text', 'last_message_at', 'last_customer_message_at'];
}
