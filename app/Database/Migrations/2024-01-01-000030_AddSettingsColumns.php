<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSettingsColumns extends Migration
{
    public function up()
    {
        // Add settings columns to accounts table
        $this->forge->addColumn('accounts', [
            'timezone' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'UTC',
                'null'       => false,
                'after'      => 'name',
            ],
            'notification_preferences' => [
                'type'  => 'JSON',
                'null'  => true,
                'after' => 'timezone',
            ],
            'api_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'notification_preferences',
            ],
        ]);

        $this->db->query('ALTER TABLE accounts ADD UNIQUE KEY `accounts_api_key_unique` (`api_key`)');

        // Add webhook_verify_token to whatsapp_config
        $this->forge->addColumn('whatsapp_config', [
            'webhook_verify_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'status',
            ],
        ]);

        // Create webhook_logs table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'account_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'payload' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['success', 'failed'],
                'default'    => 'success',
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'processing_time_ms' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->addKey('event_type');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('webhook_logs');
    }

    public function down()
    {
        $this->forge->dropTable('webhook_logs', true);
        $this->forge->dropColumn('whatsapp_config', ['webhook_verify_token']);
        $this->forge->dropColumn('accounts', ['timezone', 'notification_preferences', 'api_key']);
    }
}
