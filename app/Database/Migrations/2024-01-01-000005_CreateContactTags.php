<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContactTags extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'contact_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'tag_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['contact_id', 'tag_id']);
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('contact_tags');
    }

    public function down()
    {
        $this->forge->dropTable('contact_tags', true);
    }
}
