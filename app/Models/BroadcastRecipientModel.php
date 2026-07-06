<?php

namespace App\Models;

class BroadcastRecipientModel extends BaseModel
{
    protected $table         = 'broadcast_recipients';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'broadcast_id', 'contact_id', 'variables', 'status',
        'whatsapp_message_id', 'error_message', 'sent_at', 'delivered_at', 'read_at',
        'created_at', 'updated_at',
    ];
}
