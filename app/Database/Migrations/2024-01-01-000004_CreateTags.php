<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTags extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'account_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'color'      => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['account_id', 'name']);
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tags');
    }

    public function down()
    {
        $this->forge->dropTable('tags', true);
    }
}
