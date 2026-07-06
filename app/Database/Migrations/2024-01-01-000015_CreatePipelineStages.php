<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePipelineStages extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'CHAR', 'constraint' => 36],
            'pipeline_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'position'    => ['type' => 'INT', 'default' => 0],
            'color'       => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#3B82F6'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('pipeline_id', 'pipelines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pipeline_stages');
    }

    public function down()
    {
        $this->forge->dropTable('pipeline_stages', true);
    }
}
