<?php

namespace App\Models;

class MessageTemplateModel extends BaseModel
{
    protected $table         = 'message_templates';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'account_id', 'waba_id', 'name', 'language', 'category',
        'header_type', 'header_content', 'body_text', 'footer_text',
        'buttons', 'sample_values', 'status', 'meta_template_id',
        'quality_score', 'created_at', 'updated_at',
    ];
}
