<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeals extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'pipeline_id'         => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'stage_id'            => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'contact_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'conversation_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'title'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'value'               => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'currency'            => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'INR'],
            'status'              => ['type' => 'ENUM', 'constraint' => ['open', 'won', 'lost'], 'default' => 'open'],
            'expected_close_date' => ['type' => 'DATE', 'null' => true],
            'assigned_agent_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['account_id', 'status']);
        $this->forge->addKey(['pipeline_id', 'stage_id']);
        $this->forge->addForeignKey('pipeline_id', 'pipelines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('stage_id', 'pipeline_stages', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('deals');
    }

    public function down()
    {
        $this->forge->dropTable('deals', true);
    }
}
