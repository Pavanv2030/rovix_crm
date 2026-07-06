<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProfiles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'CHAR', 'constraint' => 36],
            'user_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'account_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'full_name'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'avatar_url'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'account_role'  => ['type' => 'ENUM', 'constraint' => ['owner', 'admin', 'agent', 'viewer'], 'default' => 'owner'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('profiles');
    }

    public function down()
    {
        $this->forge->dropTable('profiles', true);
    }
}
