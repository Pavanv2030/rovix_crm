<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLeadStatusToConversations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('conversations', [
            'lead_status_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'status'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('conversations', 'lead_status_id');
    }
}
