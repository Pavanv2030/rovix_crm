<?php

namespace App\Models;

class MediaFileModel extends BaseModel
{
    protected $table         = 'media_files';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'account_id', 'file_path', 'mime_type', 'file_size', 'original_filename', 'media_type', 'created_at', 'last_accessed_at'];
}
