<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccounts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'owner_user_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'default_currency'=> ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'INR'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('accounts');
    }

    public function down()
    {
        $this->forge->dropTable('accounts', true);
    }
}
