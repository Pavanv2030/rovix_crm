<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixMessageContentType extends Migration
{
    public function up()
    {
        $this->db->query(
            "ALTER TABLE messages MODIFY COLUMN content_type VARCHAR(50) NOT NULL DEFAULT 'text'"
        );
    }

    public function down()
    {
        $this->db->query(
            "ALTER TABLE messages MODIFY COLUMN content_type ENUM(
                'text','image','video','document','audio','sticker',
                'location','interactive','template','reaction','order'
            ) NOT NULL DEFAULT 'text'"
        );
    }
}
