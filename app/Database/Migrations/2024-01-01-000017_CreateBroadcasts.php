<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBroadcasts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'template_name'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'template_language' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'en'],
            'audience_filter'   => ['type' => 'JSON', 'null' => true],
            'status'            => ['type' => 'ENUM', 'constraint' => ['draft', 'scheduled', 'sending', 'sent', 'failed'], 'default' => 'draft'],
            'scheduled_at'      => ['type' => 'DATETIME', 'null' => true],
            'total_recipients'  => ['type' => 'INT', 'default' => 0],
            'sent_count'        => ['type' => 'INT', 'default' => 0],
            'delivered_count'   => ['type' => 'INT', 'default' => 0],
            'read_count'        => ['type' => 'INT', 'default' => 0],
            'replied_count'     => ['type' => 'INT', 'default' => 0],
            'failed_count'      => ['type' => 'INT', 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('broadcasts');
    }

    public function down()
    {
        $this->forge->dropTable('broadcasts', true);
    }
}
