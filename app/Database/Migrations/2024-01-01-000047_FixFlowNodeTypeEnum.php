<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixFlowNodeTypeEnum extends Migration
{
    public function up()
    {
        // node_type's ENUM was never widened as new node types were added to
        // FlowNodeSchemas (send_media_buttons, url_button, request_location,
        // collect_form, add_to_group, send_catalog, send_product,
        // http_request). Saving any of those silently coerced to '' under
        // MySQL's non-strict mode (no error thrown) — the node's config was
        // preserved, but its type label was destroyed, so it fell through
        // every switch statement's default case at execution time and
        // silently did nothing.
        $this->db->query(
            "ALTER TABLE flow_nodes MODIFY node_type ENUM(
                'start','send_message','send_buttons','send_list','send_media',
                'send_media_buttons','url_button','request_location',
                'collect_input','collect_form','condition','set_tag',
                'add_to_group','handoff','end','send_catalog','send_product',
                'http_request'
            ) NOT NULL"
        );

        // Best-effort backfill for rows already corrupted to '' before this
        // fix, inferring the original type from config keys unique enough
        // to trust. Anything ambiguous (e.g. request_location vs
        // send_message — both can be just {message_text, next_node}) is
        // left as '' rather than risk silently mis-assigning the wrong type.
        $rows = $this->db->table('flow_nodes')->where('node_type', '')->get()->getResultArray();
        foreach ($rows as $row) {
            $config  = json_decode($row['config'] ?? '{}', true) ?? [];
            $guessed = match (true) {
                isset($config['button_url'])                                     => 'url_button',
                isset($config['group_id'])                                       => 'add_to_group',
                isset($config['product_retailer_id'])                            => 'send_product',
                isset($config['fields']) && array_key_exists('completion_message', $config) => 'collect_form',
                isset($config['method'], $config['url'])                         => 'http_request',
                isset($config['media_type'], $config['buttons'])                 => 'send_media_buttons',
                isset($config['footer_text'])                                     => 'send_catalog',
                default => null,
            };
            if ($guessed) {
                $this->db->table('flow_nodes')->where('id', $row['id'])->update(['node_type' => $guessed]);
            }
        }
    }

    public function down()
    {
        $this->db->query(
            "ALTER TABLE flow_nodes MODIFY node_type ENUM(
                'start','send_message','send_buttons','send_list','send_media',
                'collect_input','condition','set_tag','handoff','end'
            ) NOT NULL"
        );
    }
}
