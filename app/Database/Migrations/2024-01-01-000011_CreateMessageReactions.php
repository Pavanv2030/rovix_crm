<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMessageReactions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'message_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'conversation_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'actor_type'      => ['type' => 'ENUM', 'constraint' => ['agent', 'customer'], 'null' => false],
            'actor_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'emoji'           => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('message_id', 'messages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('message_reactions');
    }

    public function down()
    {
        $this->forge->dropTable('message_reactions', true);
    }
}
