<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContactNotes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'contact_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'user_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'note_text'  => ['type' => 'TEXT', 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('contact_notes');
    }

    public function down()
    {
        $this->forge->dropTable('contact_notes', true);
    }
}
