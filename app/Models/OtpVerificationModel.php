<?php

namespace App\Models;

use CodeIgniter\Model;

class OtpVerificationModel extends Model
{
    protected $table         = 'otp_verifications';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = false;
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id', 'account_id', 'phone_number', 'otp_code', 'is_verified',
        'attempts', 'expires_at', 'created_at',
    ];
    protected $useTimestamps = false;

    // NOTE: this model deliberately extends CodeIgniter\Model rather than
    // App\Models\BaseModel, because BaseModel's scoping reads session()
    // and OTP flows also run from unauthenticated/queue contexts. Every
    // method here therefore takes an explicit $accountId — callers must
    // never query this table without one.

    public function getLatestForPhone(string $phone, string $accountId): ?array
    {
        return $this->where('account_id', $accountId)
            ->where('phone_number', $phone)
            ->where('is_verified', 0)
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function deleteUnverifiedForPhone(string $phone, string $accountId): void
    {
        $this->where('account_id', $accountId)
            ->where('phone_number', $phone)
            ->where('is_verified', 0)
            ->delete();
    }
}
