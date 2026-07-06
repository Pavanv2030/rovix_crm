<?php

namespace App\Models;

class ActivityLogModel extends BaseModel
{
    protected $table         = 'activity_logs';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'account_id', 'user_id', 'action',
        'entity_type', 'entity_id', 'metadata', 'ip_address', 'created_at',
    ];

    public static function record(string $action, ?string $entityType = null, ?string $entityId = null, ?array $metadata = null): void
    {
        $model = new self();
        $model->insert([
            'id'          => generate_uuid(),
            'account_id'  => session('account_id'),
            'user_id'     => session('user_id'),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'metadata'    => $metadata ? json_encode($metadata) : null,
            'ip_address'  => service('request')->getIPAddress(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
