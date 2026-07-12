<?php

namespace App\Models;

class PasswordResetModel extends BaseModel
{
    protected $table         = 'password_resets';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'profile_id', 'token_hash', 'expires_at', 'used_at', 'created_at'];
    protected $useTimestamps   = false;

    private function hasAccountId(): bool
    {
        return false;
    }
}