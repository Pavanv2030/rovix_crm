<?php

namespace App\Models;

class AccountInvitationModel extends BaseModel
{
    protected $table         = 'account_invitations';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'account_id', 'email', 'role', 'token_hash', 'expires_at', 'accepted_at', 'accepted_by_user_id', 'invited_by_user_id', 'created_at'];
}
