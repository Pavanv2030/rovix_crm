<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BroadcastModel / BroadcastRecipientModel / reports expect columns that
 * were never added to the original broadcast table migrations.
 */
class AddBroadcastMissingColumns extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('broadcasts')) {
            $broadcastCols = [];

            if (!$this->db->fieldExists('variable_map', 'broadcasts')) {
                $broadcastCols['variable_map'] = [
                    'type'  => 'JSON',
                    'null'  => true,
                    'after' => 'audience_filter',
                ];
            }

            if (!$this->db->fieldExists('batch_size', 'broadcasts')) {
                $broadcastCols['batch_size'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 50,
                    'null'       => false,
                    'after'      => 'variable_map',
                ];
            }

            if (!$this->db->fieldExists('sent_at', 'broadcasts')) {
                $broadcastCols['sent_at'] = [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'scheduled_at',
                ];
            }

            if (!$this->db->fieldExists('cancelled_at', 'broadcasts')) {
                $broadcastCols['cancelled_at'] = [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'sent_at',
                ];
            }

            if (!$this->db->fieldExists('created_by', 'broadcasts')) {
                $broadcastCols['created_by'] = [
                    'type'       => 'CHAR',
                    'constraint' => 36,
                    'null'       => true,
                    'after'      => 'failed_count',
                ];
            }

            if ($broadcastCols !== []) {
                $this->forge->addColumn('broadcasts', $broadcastCols);
            }
        }

        if ($this->db->tableExists('broadcast_recipients')) {
            $recipientCols = [];

            if (!$this->db->fieldExists('sent_at', 'broadcast_recipients')) {
                $recipientCols['sent_at'] = [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'error_message',
                ];
            }

            if (!$this->db->fieldExists('delivered_at', 'broadcast_recipients')) {
                $recipientCols['delivered_at'] = [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'sent_at',
                ];
            }

            if (!$this->db->fieldExists('read_at', 'broadcast_recipients')) {
                $recipientCols['read_at'] = [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'delivered_at',
                ];
            }

            if ($recipientCols !== []) {
                $this->forge->addColumn('broadcast_recipients', $recipientCols);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('broadcasts')) {
            $drop = [];
            foreach (['created_by', 'cancelled_at', 'sent_at', 'batch_size', 'variable_map'] as $field) {
                if ($this->db->fieldExists($field, 'broadcasts')) {
                    $drop[] = $field;
                }
            }
            if ($drop !== []) {
                $this->forge->dropColumn('broadcasts', $drop);
            }
        }

        if ($this->db->tableExists('broadcast_recipients')) {
            $drop = [];
            foreach (['read_at', 'delivered_at', 'sent_at'] as $field) {
                if ($this->db->fieldExists($field, 'broadcast_recipients')) {
                    $drop[] = $field;
                }
            }
            if ($drop !== []) {
                $this->forge->dropColumn('broadcast_recipients', $drop);
            }
        }
    }
}
