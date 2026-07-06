<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAutomationLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'CHAR', 'constraint' => 36],
            'automation_id'  => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'contact_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'trigger_event'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'steps_executed' => ['type' => 'JSON', 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['running', 'completed', 'failed', 'skipped'], 'default' => 'running'],
            'error_message'  => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('automation_id', 'automations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('automation_logs');
    }

    public function down()
    {
        $this->forge->dropTable('automation_logs', true);
    }
}
