<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountInvitations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'role'                => ['type' => 'ENUM', 'constraint' => ['admin', 'agent', 'viewer'], 'null' => false],
            'token_hash'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'expires_at'          => ['type' => 'DATETIME', 'null' => false],
            'accepted_at'         => ['type' => 'DATETIME', 'null' => true],
            'accepted_by_user_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('account_invitations');
    }

    public function down()
    {
        $this->forge->dropTable('account_invitations', true);
    }
}
