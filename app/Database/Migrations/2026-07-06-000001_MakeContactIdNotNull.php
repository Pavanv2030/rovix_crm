<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeContactIdNotNull extends Migration
{
    public function up()
    {
        // Drop foreign key constraints first
        $this->db->query("ALTER TABLE conversations DROP FOREIGN KEY conversations_contact_id_foreign");
        $this->db->query("ALTER TABLE deals DROP FOREIGN KEY deals_contact_id_foreign");

        // Make contact_id NOT NULL in conversations (after cleaning orphaned records)
        $this->db->query("ALTER TABLE conversations MODIFY contact_id CHAR(36) NOT NULL");

        // Make contact_id NOT NULL in deals (after cleaning orphaned records)
        $this->db->query("ALTER TABLE deals MODIFY contact_id CHAR(36) NOT NULL");

        // Re-add foreign key constraints with RESTRICT (no cascade)
        $this->db->query("
            ALTER TABLE conversations
            ADD CONSTRAINT conversations_contact_id_foreign
            FOREIGN KEY (contact_id) REFERENCES contacts(id)
            ON DELETE RESTRICT ON UPDATE CASCADE
        ");

        $this->db->query("
            ALTER TABLE deals
            ADD CONSTRAINT deals_contact_id_foreign
            FOREIGN KEY (contact_id) REFERENCES contacts(id)
            ON DELETE RESTRICT ON UPDATE CASCADE
        ");
    }

    public function down()
    {
        // Drop foreign key constraints
        $this->db->query("ALTER TABLE conversations DROP FOREIGN KEY conversations_contact_id_foreign");
        $this->db->query("ALTER TABLE deals DROP FOREIGN KEY deals_contact_id_foreign");

        // Revert to NULL
        $this->db->query("ALTER TABLE conversations MODIFY contact_id CHAR(36) NULL");
        $this->db->query("ALTER TABLE deals MODIFY contact_id CHAR(36) NULL");

        // Re-add foreign key constraints with original behavior
        $this->db->query("
            ALTER TABLE conversations
            ADD CONSTRAINT conversations_contact_id_foreign
            FOREIGN KEY (contact_id) REFERENCES contacts(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        $this->db->query("
            ALTER TABLE deals
            ADD CONSTRAINT deals_contact_id_foreign
            FOREIGN KEY (contact_id) REFERENCES contacts(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");
    }
}
