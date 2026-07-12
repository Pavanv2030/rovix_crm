<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAgentTimeLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
            ],
            'account_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
            ],
            'agent_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
            ],
            'log_date' => [
                'type' => 'DATE',
            ],
            'hours_logged' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('account_id');
        $this->forge->addKey('agent_id');
        $this->forge->addKey(['account_id', 'agent_id', 'log_date']);
        $this->forge->createTable('agent_time_logs');
    }

    public function down()
    {
        $this->forge->dropTable('agent_time_logs');
    }
}
