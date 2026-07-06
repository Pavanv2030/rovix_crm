<?php

namespace App\Models;

class ConversationStatusModel extends BaseModel
{
    protected $table         = 'conversation_statuses';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'name', 'color', 'auto_reply_message', 'reply_mode', 'template_id', 'template_header_url', 'use_ai', 'ai_instruction', 'sort_order', 'created_at', 'updated_at'];

    /**
     * Accounts seeded before "Hot Lead" was added to the default list (or
     * that already had custom statuses when they first opened the inbox)
     * never got one via seedDefaultLeadStatusesIfNone(). The webhook's
     * "Interested" button-click handler needs this status to exist
     * regardless, so it creates it lazily on first use instead of requiring
     * a retroactive migration.
     */
    public function ensureHotLeadExists(string $accountId): string
    {
        $existing = $this->where('account_id', $accountId)->where('name', 'Hot Lead')->first();
        if ($existing) {
            return $existing['id'];
        }

        $maxSort = $this->where('account_id', $accountId)->selectMax('sort_order')->first();
        $id = $this->insert([
            'account_id' => $accountId,
            'name'       => 'Hot Lead',
            'color'      => '#F97316',
            'sort_order' => (int) ($maxSort['sort_order'] ?? 0) + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }
}
