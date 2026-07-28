<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ContactModel and ContactsController expect CRM fields that were never
 * added to the contacts table (assigned_agent_id, channel, vertical, etc.).
 */
class AddContactCrmFields extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('contacts')) {
            return;
        }

        $columns = [];

        if (!$this->db->fieldExists('channel', 'contacts')) {
            $columns['channel'] = [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'avatar_url',
            ];
        }

        if (!$this->db->fieldExists('vertical', 'contacts')) {
            $columns['vertical'] = [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'channel',
            ];
        }

        if (!$this->db->fieldExists('status', 'contacts')) {
            $columns['status'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => 'New',
                'after'      => 'vertical',
            ];
        }

        if (!$this->db->fieldExists('assigned_agent_id', 'contacts')) {
            $columns['assigned_agent_id'] = [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'status',
            ];
        }

        if (!$this->db->fieldExists('follow_up_date', 'contacts')) {
            $columns['follow_up_date'] = [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'assigned_agent_id',
            ];
        }

        if ($columns !== []) {
            $this->forge->addColumn('contacts', $columns);
        }

        // Index for filtering/joining by assigned agent
        if ($this->db->fieldExists('assigned_agent_id', 'contacts')) {
            $hasAgentKey = false;
            foreach ($this->db->getIndexData('contacts') as $key) {
                if (in_array('assigned_agent_id', $key->fields ?? [], true)) {
                    $hasAgentKey = true;
                    break;
                }
            }
            if (!$hasAgentKey) {
                $this->db->query('ALTER TABLE `contacts` ADD INDEX `contacts_assigned_agent_id` (`assigned_agent_id`)');
            }
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists('contacts')) {
            return;
        }

        $drop = [];
        foreach (['follow_up_date', 'assigned_agent_id', 'status', 'vertical', 'channel'] as $field) {
            if ($this->db->fieldExists($field, 'contacts')) {
                $drop[] = $field;
            }
        }

        if ($drop !== []) {
            $this->forge->dropColumn('contacts', $drop);
        }
    }
}
