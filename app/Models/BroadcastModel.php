<?php

namespace App\Models;

class BroadcastModel extends BaseModel
{
    protected $table         = 'broadcasts';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'account_id', 'name', 'template_name', 'template_language', 'header_media_url',
        'audience_filter', 'variable_map', 'batch_size', 'status', 'scheduled_at', 'sent_at', 'cancelled_at',
        'total_recipients', 'sent_count', 'delivered_count', 'read_count',
        'replied_count', 'failed_count', 'created_by', 'created_at', 'updated_at',
    ];
}
