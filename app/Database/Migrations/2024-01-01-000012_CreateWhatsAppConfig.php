<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWhatsAppConfig extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'phone_number_id'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'waba_id'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'access_token'        => ['type' => 'TEXT', 'null' => true],
            'business_name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['disconnected', 'connected', 'registered'], 'default' => 'disconnected'],
            'subscription_status' => ['type' => 'ENUM', 'constraint' => ['inactive', 'active'], 'default' => 'inactive'],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('account_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('whatsapp_config');
    }

    public function down()
    {
        $this->forge->dropTable('whatsapp_config', true);
    }
}
