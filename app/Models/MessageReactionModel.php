<?php

namespace App\Models;

class MessageReactionModel extends BaseModel
{
    protected $table         = 'message_reactions';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'message_id', 'conversation_id', 'actor_type', 'actor_id', 'emoji', 'created_at'];
}
