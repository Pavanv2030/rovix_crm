<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widens four ENUMs that drifted behind the application code.
 *
 * The DB runs non-strict (Config\Database::$strictOn = false), so every one of
 * these mismatches was stored silently as '' instead of raising 1265 — data
 * corruption with no error. Each value below is already written by shipped code.
 */
class FixEnumDrift extends Migration
{
    public function up()
    {
        // 1. messages.sender_type — 'bot' is written by 7 call sites
        //    (WebhookController, AutomationEngine::logToInbox, MessageSender,
        //    SendAppointmentReminders, ...) and read back by ReportsController.
        $this->db->query(
            "ALTER TABLE messages MODIFY sender_type
             ENUM('agent','customer','system','bot') NOT NULL"
        );

        // Recover rows already corrupted to '' by bot sends.
        $this->db->query(
            "UPDATE messages SET sender_type = 'bot' WHERE sender_type = ''"
        );

        // 2. flow_nodes.node_type — send_template, appointment_booking and
        //    trigger_flow are implemented in FlowEngine and offered by
        //    FlowNodeSchemas, but were never added to the ENUM.
        $this->db->query(
            "ALTER TABLE flow_nodes MODIFY node_type ENUM(
                'start','send_message','send_buttons','send_list','send_media',
                'send_media_buttons','url_button','request_location',
                'collect_input','collect_form','condition','set_tag',
                'add_to_group','handoff','end','send_catalog','send_product',
                'http_request','ai_node','send_template','appointment_booking',
                'trigger_flow'
            ) NOT NULL"
        );

        // 3. flow_runs.status — FlowEngine::endFlow() writes 'terminated'.
        $this->db->query(
            "ALTER TABLE flow_runs MODIFY status
             ENUM('active','completed','handed_off','timed_out','failed','terminated')
             NOT NULL DEFAULT 'active'"
        );

        // 4. message_templates.header_type — the create-template UI offers
        //    'carousel'.
        $this->db->query(
            "ALTER TABLE message_templates MODIFY header_type
             ENUM('none','text','image','video','document','carousel')
             NOT NULL DEFAULT 'none'"
        );
    }

    public function down()
    {
        // Values outside the narrower sets would be truncated to '' on the way
        // back down, so remap them onto their closest legal predecessor first.
        $this->db->query(
            "UPDATE messages SET sender_type = 'system' WHERE sender_type = 'bot'"
        );
        $this->db->query(
            "ALTER TABLE messages MODIFY sender_type
             ENUM('agent','customer','system') NOT NULL"
        );

        $this->db->query(
            "DELETE FROM flow_nodes WHERE node_type IN
             ('send_template','appointment_booking','trigger_flow')"
        );
        // Restores 000050's list — which includes ai_node. Dropping back to
        // 000047's 18 values here would truncate every ai_node row to ''.
        $this->db->query(
            "ALTER TABLE flow_nodes MODIFY node_type ENUM(
                'start','send_message','send_buttons','send_list','send_media',
                'send_media_buttons','url_button','request_location',
                'collect_input','collect_form','condition','set_tag',
                'add_to_group','handoff','end','send_catalog','send_product',
                'http_request','ai_node'
            ) NOT NULL"
        );

        $this->db->query(
            "UPDATE flow_runs SET status = 'failed' WHERE status = 'terminated'"
        );
        $this->db->query(
            "ALTER TABLE flow_runs MODIFY status
             ENUM('active','completed','handed_off','timed_out','failed')
             NOT NULL DEFAULT 'active'"
        );

        $this->db->query(
            "UPDATE message_templates SET header_type = 'none' WHERE header_type = 'carousel'"
        );
        $this->db->query(
            "ALTER TABLE message_templates MODIFY header_type
             ENUM('none','text','image','video','document')
             NOT NULL DEFAULT 'none'"
        );
    }
}
