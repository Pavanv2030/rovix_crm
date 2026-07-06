<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMediaFiles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'file_path'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
            'mime_type'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'file_size'         => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'media_type'        => ['type' => 'ENUM', 'constraint' => ['image', 'video', 'document', 'audio'], 'null' => false],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'last_accessed_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('media_files');
    }

    public function down()
    {
        $this->forge->dropTable('media_files', true);
    }
}
