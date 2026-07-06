<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTemplateModeToConversationStatuses extends Migration
{
    public function up()
    {
        $this->forge->addColumn('conversation_statuses', [
            'reply_mode'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'static', 'after' => 'auto_reply_message'],
            'template_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'reply_mode'],
        ]);

        // Backfill from the existing use_ai flag so statuses configured
        // before this feature keep behaving the same way.
        $this->db->query("UPDATE conversation_statuses SET reply_mode = 'ai' WHERE use_ai = 1");
    }

    public function down()
    {
        $this->forge->dropColumn('conversation_statuses', 'reply_mode');
        $this->forge->dropColumn('conversation_statuses', 'template_id');
    }
}
