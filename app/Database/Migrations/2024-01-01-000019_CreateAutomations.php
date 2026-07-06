<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAutomations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'user_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'trigger_type'     => ['type' => 'ENUM', 'constraint' => ['new_message_received', 'first_inbound_message', 'keyword_match', 'new_contact_created', 'conversation_assigned', 'tag_added', 'time_based'], 'null' => false],
            'trigger_config'   => ['type' => 'JSON', 'null' => true],
            'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'execution_count'  => ['type' => 'INT', 'default' => 0],
            'last_executed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('automations');
    }

    public function down()
    {
        $this->forge->dropTable('automations', true);
    }
}
