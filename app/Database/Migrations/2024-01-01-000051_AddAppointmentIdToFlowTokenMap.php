<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAppointmentIdToFlowTokenMap extends Migration
{
    public function up()
    {
        // Set when a flow_token was issued to reschedule an EXISTING
        // appointment (customer tapped "Reschedule" on the booking page)
        // rather than book a brand new one — lets processFlowCompletion()
        // update that same row instead of inserting a duplicate.
        $this->forge->addColumn('flow_token_map', [
            'appointment_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'appointment_type_id'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('flow_token_map', 'appointment_id');
    }
}
