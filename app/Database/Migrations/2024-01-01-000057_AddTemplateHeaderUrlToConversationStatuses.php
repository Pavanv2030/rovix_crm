<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTemplateHeaderUrlToConversationStatuses extends Migration
{
    public function up()
    {
        // Image/video/document header templates need a real media URL
        // supplied at send time — WhatsApp doesn't let that be baked into
        // the template itself the way a text header can.
        $this->forge->addColumn('conversation_statuses', [
            'template_header_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'template_id'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('conversation_statuses', 'template_header_url');
    }
}
