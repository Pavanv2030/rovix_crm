<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContacts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'phone'            => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'phone_normalized' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'company'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'avatar_url'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['account_id', 'phone_normalized']);
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('contacts');
    }

    public function down()
    {
        $this->forge->dropTable('contacts', true);
    }
}
