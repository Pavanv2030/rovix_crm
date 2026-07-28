<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCatalogProductsCache extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('catalog_products', 'whatsapp_config')) {
            $this->db->query("ALTER TABLE whatsapp_config ADD COLUMN catalog_products MEDIUMTEXT NULL AFTER catalog_synced_at");
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('catalog_products', 'whatsapp_config')) {
            $this->db->query("ALTER TABLE whatsapp_config DROP COLUMN catalog_products");
        }
    }
}
