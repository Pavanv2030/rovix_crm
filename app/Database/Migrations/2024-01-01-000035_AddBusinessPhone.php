<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBusinessPhone extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('business_phone', 'whatsapp_config')) {
            $this->db->query("ALTER TABLE whatsapp_config ADD COLUMN business_phone VARCHAR(30) NULL AFTER phone_number_id");
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('business_phone', 'whatsapp_config')) {
            $this->db->query("ALTER TABLE whatsapp_config DROP COLUMN business_phone");
        }
    }
}
