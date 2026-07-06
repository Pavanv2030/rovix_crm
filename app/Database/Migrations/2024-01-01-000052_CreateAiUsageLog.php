<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAiUsageLog extends Migration
{
    public function up()
    {
        // OpenAI has no public endpoint to check remaining balance for a
        // regular API key — the only reliable number is what WE track from
        // each response's own token counts. cost_estimate is computed from
        // published per-model pricing (see OpenAiClient::PRICING) — an
        // estimate, not the actual invoiced amount.
        $this->forge->addField([
            'id'                => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'feature'           => ['type' => 'VARCHAR', 'constraint' => 50],
            'model'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'prompt_tokens'     => ['type' => 'INT', 'default' => 0],
            'completion_tokens' => ['type' => 'INT', 'default' => 0],
            'total_tokens'      => ['type' => 'INT', 'default' => 0],
            'cost_estimate'     => ['type' => 'DECIMAL', 'constraint' => '10,6', 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ai_usage_log');
    }

    public function down()
    {
        $this->forge->dropTable('ai_usage_log', true);
    }
}
