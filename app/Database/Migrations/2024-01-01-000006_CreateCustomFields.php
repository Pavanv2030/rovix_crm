<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomFields extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'field_name'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'field_type'     => ['type' => 'ENUM', 'constraint' => ['text', 'number', 'date', 'dropdown'], 'null' => false],
            'field_options'  => ['type' => 'JSON', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('custom_fields');
    }

    public function down()
    {
        $this->forge->dropTable('custom_fields', true);
    }
}
