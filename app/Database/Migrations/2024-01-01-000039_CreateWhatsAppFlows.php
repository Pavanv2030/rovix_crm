<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWhatsAppFlows extends Migration
{
    public function up()
    {
        // whatsapp_flows — Meta flow per appointment type
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'appointment_type_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'flow_id'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'flow_name'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'status'              => ['type' => 'ENUM', 'constraint' => ['draft', 'published', 'deprecated'], 'default' => 'draft'],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->createTable('whatsapp_flows');

        // flow_token_map — maps per-send token to context (account, type, contact)
        $this->forge->addField([
            'flow_token'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'appointment_type_id' => ['type' => 'CHAR', 'constraint' => 36],
            'contact_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'conversation_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('flow_token');
        $this->forge->createTable('flow_token_map');
    }

    public function down()
    {
        $this->forge->dropTable('flow_token_map', true);
        $this->forge->dropTable('whatsapp_flows', true);
    }
}
