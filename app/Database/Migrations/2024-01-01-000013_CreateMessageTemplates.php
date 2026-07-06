<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMessageTemplates extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'language'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'en'],
            'category'         => ['type' => 'ENUM', 'constraint' => ['marketing', 'utility', 'authentication'], 'null' => false],
            'header_type'      => ['type' => 'ENUM', 'constraint' => ['none', 'text', 'image', 'video', 'document'], 'default' => 'none'],
            'header_content'   => ['type' => 'TEXT', 'null' => true],
            'body_text'        => ['type' => 'TEXT', 'null' => false],
            'footer_text'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'buttons'          => ['type' => 'JSON', 'null' => true],
            'sample_values'    => ['type' => 'JSON', 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['draft', 'pending', 'approved', 'rejected', 'paused', 'disabled', 'in_appeal'], 'default' => 'draft'],
            'meta_template_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'quality_score'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['account_id', 'status']);
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('message_templates');
    }

    public function down()
    {
        $this->forge->dropTable('message_templates', true);
    }
}
