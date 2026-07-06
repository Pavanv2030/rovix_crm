<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOtpVerifications extends Migration
{
    public function up(): void
    {
        // OTP verifications table
        $this->forge->addField([
            'id'           => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'phone_number' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'otp_code'     => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => false],
            'is_verified'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'attempts'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'expires_at'   => ['type' => 'DATETIME', 'null' => false],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('phone_number');
        $this->forge->createTable('otp_verifications', true);

        // Add phone verification flag to contacts
        if ($this->db->tableExists('contacts') && !$this->db->fieldExists('is_phone_verified', 'contacts')) {
            $this->forge->addColumn('contacts', [
                'is_phone_verified' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'phone_normalized',
                ],
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('otp_verifications', true);

        if ($this->db->tableExists('contacts') && $this->db->fieldExists('is_phone_verified', 'contacts')) {
            $this->forge->dropColumn('contacts', 'is_phone_verified');
        }
    }
}
