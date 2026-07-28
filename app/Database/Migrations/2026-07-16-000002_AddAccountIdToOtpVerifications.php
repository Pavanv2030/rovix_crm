<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * otp_verifications was keyed on phone_number alone, with no tenant column.
 * Rows were therefore shared across every account: one tenant could lock,
 * verify, or delete another tenant's pending OTP, and use the API responses
 * as an existence oracle for arbitrary phone numbers.
 */
class AddAccountIdToOtpVerifications extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('account_id', 'otp_verifications')) {
            $this->forge->addColumn('otp_verifications', [
                'account_id' => [
                    'type'       => 'CHAR',
                    'constraint' => 36,
                    'null'       => true, // nullable so existing rows survive
                    'after'      => 'id',
                ],
            ]);
        }

        // Pending OTPs from before this migration cannot be attributed to a
        // tenant. They are short-lived by design, so drop them rather than
        // leave unscoped rows that would still be readable by every account.
        $this->db->query("DELETE FROM otp_verifications WHERE account_id IS NULL");

        $this->db->query(
            "ALTER TABLE otp_verifications MODIFY account_id CHAR(36) NOT NULL"
        );

        // Create the composite index BEFORE the FK: MySQL will reuse its
        // leftmost column for the constraint instead of auto-creating a
        // redundant single-column index on account_id.
        $this->db->query(
            "ALTER TABLE otp_verifications
             ADD INDEX otp_account_phone (account_id, phone_number)"
        );

        $this->db->query(
            "ALTER TABLE otp_verifications
             ADD CONSTRAINT otp_verifications_account_id_foreign
             FOREIGN KEY (account_id) REFERENCES accounts(id)
             ON DELETE CASCADE ON UPDATE CASCADE"
        );
    }

    public function down()
    {
        // FK first — the index it relies on cannot be dropped while it exists.
        $this->db->query(
            "ALTER TABLE otp_verifications DROP FOREIGN KEY otp_verifications_account_id_foreign"
        );
        $this->db->query(
            "ALTER TABLE otp_verifications DROP INDEX otp_account_phone"
        );

        if ($this->db->fieldExists('account_id', 'otp_verifications')) {
            $this->forge->dropColumn('otp_verifications', 'account_id');
        }
    }
}
