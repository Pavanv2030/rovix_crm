<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCatalogProductsCache extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE whatsapp_config ADD COLUMN IF NOT EXISTS catalog_products MEDIUMTEXT NULL AFTER catalog_synced_at");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE whatsapp_config DROP COLUMN IF EXISTS catalog_products");
    }
}
