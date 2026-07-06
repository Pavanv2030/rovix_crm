<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHeaderMediaUrlToBroadcasts extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('header_media_url', 'broadcasts')) {
            $this->forge->addColumn('broadcasts', [
                'header_media_url' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => true,
                    'after'      => 'template_language',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('header_media_url', 'broadcasts')) {
            $this->forge->dropColumn('broadcasts', 'header_media_url');
        }
    }
}
