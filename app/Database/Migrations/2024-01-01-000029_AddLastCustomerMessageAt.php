<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLastCustomerMessageAt extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('last_customer_message_at', 'conversations')) {
            $this->forge->addColumn('conversations', [
                'last_customer_message_at' => [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'last_message_at',
                ],
            ]);
        }

        // Back-fill from the most recent customer message per conversation
        $this->db->query("
            UPDATE conversations c
            JOIN (
                SELECT conversation_id, MAX(created_at) AS last_at
                FROM messages
                WHERE sender_type = 'customer'
                GROUP BY conversation_id
            ) m ON m.conversation_id = c.id
            SET c.last_customer_message_at = m.last_at
            WHERE c.last_customer_message_at IS NULL
        ");
    }

    public function down(): void
    {
        if ($this->db->fieldExists('last_customer_message_at', 'conversations')) {
            $this->forge->dropColumn('conversations', 'last_customer_message_at');
        }
    }
}
