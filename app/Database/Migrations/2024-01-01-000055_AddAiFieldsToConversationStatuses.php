<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAiFieldsToConversationStatuses extends Migration
{
    public function up()
    {
        $this->forge->addColumn('conversation_statuses', [
            'use_ai'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'auto_reply_message'],
            'ai_instruction' => ['type' => 'TEXT', 'null' => true, 'after' => 'use_ai'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('conversation_statuses', 'use_ai');
        $this->forge->dropColumn('conversation_statuses', 'ai_instruction');
    }
}
