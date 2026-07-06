<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAppointmentFollowUp extends Migration
{
    public function up()
    {
        $this->forge->addColumn('appointments', [
            'follow_up_sent_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'reminder_sent_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('appointments', 'follow_up_sent_at');
    }
}
