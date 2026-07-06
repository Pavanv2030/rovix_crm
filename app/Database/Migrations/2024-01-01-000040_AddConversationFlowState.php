<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConversationFlowState extends Migration
{
    public function up()
    {
        $this->forge->addColumn('conversations', [
            'flow_state' => ['type' => 'JSON', 'null' => true, 'after' => 'last_message_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('conversations', 'flow_state');
    }
}
