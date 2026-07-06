<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConversationStatuses extends Migration
{
    public function up()
    {
        // User-defined lead statuses (separate from conversations.status,
        // which is the inbox's own open/pending/closed state) — deliberately
        // a real table with an FK, not an ENUM. Widening an ENUM later (to
        // add/rename a status) has already silently corrupted data twice
        // elsewhere in this app when a value fell outside the fixed list.
        $this->forge->addField([
            'id'                 => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'         => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'color'              => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#3B82F6'],
            'auto_reply_message' => ['type' => 'TEXT', 'null' => true],
            'sort_order'         => ['type' => 'INT', 'default' => 0],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('conversation_statuses');
    }

    public function down()
    {
        $this->forge->dropTable('conversation_statuses', true);
    }
}
