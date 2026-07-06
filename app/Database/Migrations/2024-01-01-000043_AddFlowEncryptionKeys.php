<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFlowEncryptionKeys extends Migration
{
    public function up()
    {
        $this->forge->addColumn('whatsapp_config', [
            'flow_public_key' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'flow_private_key' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'AES-256-GCM encrypted via App\\Libraries\\WhatsApp\\Encryption',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('whatsapp_config', ['flow_public_key', 'flow_private_key']);
    }
}
