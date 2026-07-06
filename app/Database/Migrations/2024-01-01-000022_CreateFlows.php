<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFlows extends Migration
{
    public function up()
    {
        // flows
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'trigger_keywords'=> ['type' => 'JSON', 'null' => true],
            'execution_count' => ['type' => 'INT', 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('flows');

        // flow_nodes
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'flow_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'node_key'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'node_type'  => ['type' => 'ENUM', 'constraint' => ['start', 'send_message', 'send_buttons', 'send_list', 'send_media', 'collect_input', 'condition', 'set_tag', 'handoff', 'end'], 'null' => false],
            'config'     => ['type' => 'JSON', 'null' => false],
            'position_x' => ['type' => 'FLOAT', 'null' => true],
            'position_y' => ['type' => 'FLOAT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('flow_id', 'flows', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('flow_nodes');

        // flow_runs
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'flow_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'contact_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'conversation_id'  => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['active', 'completed', 'handed_off', 'timed_out', 'failed'], 'default' => 'active'],
            'current_node_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'vars'             => ['type' => 'JSON', 'null' => true],
            'meta_message_id'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'started_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['flow_id', 'contact_id', 'status']);
        $this->forge->addForeignKey('flow_id', 'flows', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('flow_runs');

        // flow_run_events
        $this->forge->addField([
            'id'          => ['type' => 'CHAR', 'constraint' => 36],
            'flow_run_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'node_key'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'event_type'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'event_data'  => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('flow_run_id', 'flow_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('flow_run_events');
    }

    public function down()
    {
        $this->forge->dropTable('flow_run_events', true);
        $this->forge->dropTable('flow_runs', true);
        $this->forge->dropTable('flow_nodes', true);
        $this->forge->dropTable('flows', true);
    }
}
