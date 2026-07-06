<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCatalog extends Migration
{
    public function up()
    {
        $this->forge->addColumn('whatsapp_config', [
            'catalog_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'number_info_fetched_at',
            ],
            'catalog_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'catalog_id',
            ],
            'catalog_synced_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'catalog_name',
            ],
        ]);

        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'account_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'contact_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'conversation_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'catalog_id'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'order_items'     => ['type' => 'JSON', 'null' => true],
            'total_price'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'currency'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'customer_note'   => ['type' => 'TEXT', 'null' => true],
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['new', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
                'default'    => 'new',
            ],
            'wa_order_id'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'reminder_sent_at' => ['type' => 'DATETIME', 'null' => true],
            'payment_method'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['account_id', 'status']);
        $this->forge->createTable('catalog_orders');
    }

    public function down()
    {
        $this->forge->dropTable('catalog_orders', true);
        $this->forge->dropColumn('whatsapp_config', ['catalog_id', 'catalog_name', 'catalog_synced_at']);
    }
}
