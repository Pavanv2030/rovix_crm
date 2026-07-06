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
        'id', 'phone_number', 'otp_code', 'is_verified',
        'attempts', 'expires_at', 'created_at',
    ];
    protected $useTimestamps = false;

    public function getLatestForPhone(string $phone): ?array
    {
        return $this->where('phone_number', $phone)
            ->where('is_verified', 0)
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function deleteUnverifiedForPhone(string $phone): void
    {
        $this->where('phone_number', $phone)->where('is_verified', 0)->delete();
    }
}
