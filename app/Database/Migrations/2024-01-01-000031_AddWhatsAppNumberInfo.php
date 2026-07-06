<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWhatsAppNumberInfo extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE whatsapp_config
            ADD COLUMN IF NOT EXISTS display_phone_number VARCHAR(30)  NULL DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS verified_name        VARCHAR(255) NULL DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS quality_rating       VARCHAR(20)  NULL DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS name_status          VARCHAR(50)  NULL DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS account_mode         VARCHAR(20)  NULL DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS number_info_fetched_at DATETIME  NULL DEFAULT NULL
        ");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE whatsapp_config
            DROP COLUMN IF EXISTS display_phone_number,
            DROP COLUMN IF EXISTS verified_name,
            DROP COLUMN IF EXISTS quality_rating,
            DROP COLUMN IF EXISTS name_status,
            DROP COLUMN IF EXISTS account_mode,
            DROP COLUMN IF EXISTS number_info_fetched_at
        ");
    }
}
