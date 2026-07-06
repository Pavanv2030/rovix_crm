<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAiConfigs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'account_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'provider'   => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'openai'],
            'api_key'    => ['type' => 'TEXT', 'null' => false], // encrypted, see App\Libraries\WhatsApp\Encryption
            'model'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'gpt-4o-mini'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('account_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ai_configs');
    }

    public function down()
    {
        $this->forge->dropTable('ai_configs', true);
    }
}
