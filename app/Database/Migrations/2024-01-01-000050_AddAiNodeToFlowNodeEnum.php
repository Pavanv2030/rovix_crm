<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAiNodeToFlowNodeEnum extends Migration
{
    public function up()
    {
        // 000047 already ran before 'ai_node' existed as a type — that
        // migration file was edited afterward, but CI4 migrations only run
        // once (tracked by name), so the edit had no effect on already-run
        // installs. Add it here instead.
        $this->db->query(
            "ALTER TABLE flow_nodes MODIFY node_type ENUM(
                'start','send_message','send_buttons','send_list','send_media',
                'send_media_buttons','url_button','request_location',
                'collect_input','collect_form','condition','set_tag',
                'add_to_group','handoff','end','send_catalog','send_product',
                'http_request','ai_node'
            ) NOT NULL"
        );
    }

    public function down()
    {
        $this->db->query(
            "ALTER TABLE flow_nodes MODIFY node_type ENUM(
                'start','send_message','send_buttons','send_list','send_media',
                'send_media_buttons','url_button','request_location',
                'collect_input','collect_form','condition','set_tag',
                'add_to_group','handoff','end','send_catalog','send_product',
                'http_request'
            ) NOT NULL"
        );
    }
}
