<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * FlowModel / FlowEngine expect trigger_type and ai_intent_description
 * on flows; they were never added to the original CreateFlows migration.
 */
class AddFlowTriggerFields extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('flows')) {
            return;
        }

        $cols = [];

        if (!$this->db->fieldExists('trigger_type', 'flows')) {
            $cols['trigger_type'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
                'default'    => 'keyword',
                'after'      => 'trigger_keywords',
            ];
        }

        if (!$this->db->fieldExists('ai_intent_description', 'flows')) {
            $cols['ai_intent_description'] = [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'trigger_type',
            ];
        }

        if ($cols !== []) {
            $this->forge->addColumn('flows', $cols);
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists('flows')) {
            return;
        }

        $drop = [];
        foreach (['ai_intent_description', 'trigger_type'] as $field) {
            if ($this->db->fieldExists($field, 'flows')) {
                $drop[] = $field;
            }
        }
        if ($drop !== []) {
            $this->forge->dropColumn('flows', $drop);
        }
    }
}
