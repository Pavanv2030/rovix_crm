<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderContentType extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE messages MODIFY content_type ENUM('text','image','video','document','audio','sticker','location','template','interactive','reaction','order') NOT NULL DEFAULT 'text'");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE messages MODIFY content_type ENUM('text','image','video','document','audio','sticker','location','template','interactive','reaction') NOT NULL DEFAULT 'text'");
    }
}
