<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAutomationSteps extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'CHAR', 'constraint' => 36],
            'automation_id'  => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'parent_step_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'branch'         => ['type' => 'ENUM', 'constraint' => ['yes', 'no'], 'null' => true],
            'step_type'      => ['type' => 'ENUM', 'constraint' => ['send_message', 'send_template', 'add_tag', 'remove_tag', 'assign_conversation', 'update_contact_field', 'create_deal', 'wait', 'condition', 'send_webhook', 'close_conversation'], 'null' => false],
            'step_config'    => ['type' => 'JSON', 'null' => false],
            'position'       => ['type' => 'INT', 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('automation_id', 'automations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('automation_steps');
    }

    public function down()
    {
        $this->forge->dropTable('automation_steps', true);
    }
}
