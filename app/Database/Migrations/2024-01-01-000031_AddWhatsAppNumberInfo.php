<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWhatsAppNumberInfo extends Migration
{
    public function up()
    {
        $columns = [
            'display_phone_number'   => "VARCHAR(30)  NULL DEFAULT NULL",
            'verified_name'          => "VARCHAR(255) NULL DEFAULT NULL",
            'quality_rating'         => "VARCHAR(20)  NULL DEFAULT NULL",
            'name_status'            => "VARCHAR(50)  NULL DEFAULT NULL",
            'account_mode'           => "VARCHAR(20)  NULL DEFAULT NULL",
            'number_info_fetched_at' => "DATETIME  NULL DEFAULT NULL",
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'whatsapp_config')) {
                $this->db->query("ALTER TABLE whatsapp_config ADD COLUMN {$name} {$definition}");
            }
        }
    }

    public function down()
    {
        $columns = [
            'display_phone_number',
            'verified_name',
            'quality_rating',
            'name_status',
            'account_mode',
            'number_info_fetched_at',
        ];

        foreach ($columns as $name) {
            if ($this->db->fieldExists($name, 'whatsapp_config')) {
                $this->db->query("ALTER TABLE whatsapp_config DROP COLUMN {$name}");
            }
        }
    }
}
