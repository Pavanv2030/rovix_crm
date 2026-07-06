<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAppointmentTables extends Migration
{
    public function up()
    {
        // appointment_types
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'      => ['type' => 'CHAR', 'constraint' => 36],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'     => ['type' => 'TEXT', 'null' => true],
            'duration_minutes'=> ['type' => 'INT', 'default' => 30],
            'price'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'currency'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'INR'],
            'availability'    => ['type' => 'JSON', 'null' => true],
            'max_days_ahead'  => ['type' => 'INT', 'default' => 60],
            'buffer_minutes'  => ['type' => 'INT', 'default' => 0],
            'active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->createTable('appointment_types');

        // appointments
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'appointment_type_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'contact_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'conversation_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'contact_name'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'contact_phone'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'contact_email'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'scheduled_at'        => ['type' => 'DATETIME', 'null' => true],
            'end_at'              => ['type' => 'DATETIME', 'null' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['pending', 'confirmed', 'cancelled', 'completed'], 'default' => 'pending'],
            'answers'             => ['type' => 'JSON', 'null' => true],
            'google_event_id'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meet_link'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'price_paid'          => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'reminder_sent_at'    => ['type' => 'DATETIME', 'null' => true],
            'booking_token'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('booking_token');
        $this->forge->addKey(['account_id', 'status']);
        $this->forge->createTable('appointments');
    }

    public function down()
    {
        $this->forge->dropTable('appointments', true);
        $this->forge->dropTable('appointment_types', true);
    }
}
