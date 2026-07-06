<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConversations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'contact_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'status'            => ['type' => 'ENUM', 'constraint' => ['open', 'pending', 'closed'], 'default' => 'open'],
            'assigned_agent_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'unread_count'      => ['type' => 'INT', 'default' => 0],
            'last_message_text' => ['type' => 'TEXT', 'null' => true],
            'last_message_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['account_id', 'status']);
        $this->forge->addKey(['account_id', 'last_message_at']);
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('conversations');
    }

    public function down()
    {
        $this->forge->dropTable('conversations', true);
    }
}
