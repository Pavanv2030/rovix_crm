<?php

namespace App\Models;

class MessageModel extends BaseModel
{
    protected $table         = 'messages';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'conversation_id', 'account_id', 'sender_type', 'content_type', 'content_text', 'media_url', 'media_mime_type', 'media_filename', 'is_voice_note', 'status', 'whatsapp_message_id', 'reply_to_message_id', 'template_name', 'error_message', 'created_at'];
}
