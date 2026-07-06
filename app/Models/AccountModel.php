<?php

namespace App\Models;

class AccountModel extends BaseModel
{
    protected $table         = 'accounts';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'name', 'owner_user_id', 'default_currency', 'timezone', 'notification_preferences', 'api_key'];

    private function hasAccountId(): bool { return false; }
}
