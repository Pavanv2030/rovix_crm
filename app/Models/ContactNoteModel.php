<?php

namespace App\Models;

class ContactNoteModel extends BaseModel
{
    protected $table         = 'contact_notes';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $updatedField  = '';
    protected $allowedFields = ['id', 'contact_id', 'user_id', 'note_text', 'created_at'];
}
