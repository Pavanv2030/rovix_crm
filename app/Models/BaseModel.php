<?php

namespace App\Models;

class BaseModel extends \CodeIgniter\Model
{
    protected $useAutoIncrement = false;
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $returnType       = 'array';

    protected static bool $bypassAccountScope = false;

    public static function setBypassAccountScope(bool $bypass): void
    {
        static::$bypassAccountScope = $bypass;
    }

    protected function initialize(): void
    {
        parent::initialize();

        if (!static::$bypassAccountScope && $this->hasAccountId()) {
            $accountId = session('account_id');
            if ($accountId) {
                $this->where($this->table . '.account_id', $accountId);
            }
        }
    }

    private function hasAccountId(): bool
    {
        // Tables without an account_id column (junction/child tables)
        $excludedTables = [
            'accounts',
            'ci_sessions',
            'job_queue',
            'media_files',
            'contact_tags',
            'contact_custom_values',
            'contact_notes',
            'message_reactions',
            'broadcast_recipients',
            'automation_steps',
            'automation_logs',
            'flow_nodes',
            'flow_runs',
            'flow_run_events',
            'pipeline_stages',
            'otp_verifications',
        ];
        return !in_array($this->table, $excludedTables);
    }

    protected $beforeInsert = ['generateUuid', 'injectAccountId'];

    protected function generateUuid(array $data): array
    {
        // Tables with a real auto-increment int primary key (job_queue) must
        // never get a UUID string forced into that column — MySQL coerces
        // the string to a number, producing huge/colliding/out-of-range
        // ids instead of a normal sequence.
        if ($this->useAutoIncrement) {
            return $data;
        }
        if (empty($data['data']['id'])) {
            $data['data']['id'] = $this->generateUuidV4();
        }
        return $data;
    }

    protected function injectAccountId(array $data): array
    {
        if ($this->hasAccountId() && empty($data['data']['account_id'])) {
            $data['data']['account_id'] = session('account_id');
        }
        return $data;
    }

    private function generateUuidV4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
