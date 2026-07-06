<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTeamManagement extends Migration
{
    public function up()
    {
        // Add is_active and last_seen_at to profiles
        $this->forge->addColumn('profiles', [
            'is_active'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'account_role'],
            'last_seen_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'is_active'],
        ]);

        // Add email + invited_by to account_invitations
        $this->forge->addColumn('account_invitations', [
            'email'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'account_id'],
            'invited_by_user_id'  => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'accepted_by_user_id'],
        ]);

        // Create activity_logs table
        $this->forge->addField([
            'id'          => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'  => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'user_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'entity_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'metadata'    => ['type' => 'JSON', 'null' => true],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('activity_logs');
    }

    public function down()
    {
        $this->forge->dropColumn('profiles', ['is_active', 'last_seen_at']);
        $this->forge->dropColumn('account_invitations', ['email', 'invited_by_user_id']);
        $this->forge->dropTable('activity_logs', true);
    }
}
