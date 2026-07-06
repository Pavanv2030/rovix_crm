<?php

namespace App\Models;

class ProfileModel extends BaseModel
{
    protected $table         = 'profiles';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'user_id', 'account_id', 'full_name', 'email', 'password_hash', 'avatar_url', 'account_role', 'is_active', 'last_seen_at'];
}
