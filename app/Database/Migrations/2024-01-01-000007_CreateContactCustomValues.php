<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContactCustomValues extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'contact_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'custom_field_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'value'           => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('custom_field_id', 'custom_fields', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('contact_custom_values');
    }

    public function down()
    {
        $this->forge->dropTable('contact_custom_values', true);
    }
}
