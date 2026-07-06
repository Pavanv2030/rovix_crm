<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBroadcastRecipients extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'broadcast_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'contact_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'variables'           => ['type' => 'JSON', 'null' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['pending', 'sent', 'delivered', 'read', 'replied', 'failed'], 'default' => 'pending'],
            'whatsapp_message_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'error_message'       => ['type' => 'TEXT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('broadcast_id', 'broadcasts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('broadcast_recipients');
    }

    public function down()
    {
        $this->forge->dropTable('broadcast_recipients', true);
    }
}
