<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePipelines extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'account_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pipelines');
    }

    public function down()
    {
        $this->forge->dropTable('pipelines', true);
    }
}
