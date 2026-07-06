<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMessages extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'conversation_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'sender_type'         => ['type' => 'ENUM', 'constraint' => ['agent', 'customer', 'system'], 'null' => false],
            'content_type'        => ['type' => 'ENUM', 'constraint' => ['text', 'image', 'video', 'document', 'audio', 'sticker', 'location', 'template', 'interactive', 'reaction'], 'default' => 'text'],
            'content_text'        => ['type' => 'TEXT', 'null' => true],
            'media_url'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'media_mime_type'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'media_filename'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['sending', 'sent', 'delivered', 'read', 'failed'], 'default' => 'sending'],
            'whatsapp_message_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'reply_to_message_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'template_name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'error_message'       => ['type' => 'TEXT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['conversation_id', 'created_at']);
        $this->forge->addKey('whatsapp_message_id');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('conversation_id', 'conversations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('messages');
    }

    public function down()
    {
        $this->forge->dropTable('messages', true);
    }
}
