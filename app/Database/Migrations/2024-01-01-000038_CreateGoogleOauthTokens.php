<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGoogleOauthTokens extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'    => ['type' => 'CHAR', 'constraint' => 36],
            'access_token'  => ['type' => 'TEXT'],
            'refresh_token' => ['type' => 'TEXT', 'null' => true],
            'expires_at'    => ['type' => 'DATETIME', 'null' => true],
            'calendar_id'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'primary'],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('account_id');
        $this->forge->createTable('google_oauth_tokens');
    }

    public function down()
    {
        $this->forge->dropTable('google_oauth_tokens', true);
    }
}
