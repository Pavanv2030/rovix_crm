<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixAutomationStepTypeEnum extends Migration
{
    public function up()
    {
        // Widening the step_type ENUM in automation_steps to allow 'send_appointment_flow' and 'send_catalog'
        $this->db->query(
            "ALTER TABLE automation_steps MODIFY step_type ENUM(
                'send_message', 'send_template', 'send_appointment_flow', 'send_catalog',
                'add_tag', 'remove_tag', 'assign_conversation', 'update_contact_field',
                'create_deal', 'wait', 'condition', 'send_webhook', 'close_conversation'
            ) NOT NULL"
        );

        // Backfill already corrupted rows (MySQL non-strict mode sets unrecognized ENUMs to '')
        $rows = $this->db->table('automation_steps')->where('step_type', '')->get()->getResultArray();
        foreach ($rows as $row) {
            $config  = json_decode($row['step_config'] ?? '{}', true) ?? [];
            $guessed = match (true) {
                isset($config['appointment_type_id']) => 'send_appointment_flow',
                isset($config['footer_text'])          => 'send_catalog',
                default                                => null,
            };
            if ($guessed) {
                $this->db->table('automation_steps')->where('id', $row['id'])->update(['step_type' => $guessed]);
            }
        }
    }

    public function down()
    {
        $this->db->query(
            "ALTER TABLE automation_steps MODIFY step_type ENUM(
                'send_message', 'send_template', 'add_tag', 'remove_tag',
                'assign_conversation', 'update_contact_field', 'create_deal',
                'wait', 'condition', 'send_webhook', 'close_conversation'
            ) NOT NULL"
        );
    }
}
