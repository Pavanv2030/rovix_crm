<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBusinessPhone extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE whatsapp_config ADD COLUMN IF NOT EXISTS business_phone VARCHAR(30) NULL AFTER phone_number_id");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE whatsapp_config DROP COLUMN IF EXISTS business_phone");
    }
}
