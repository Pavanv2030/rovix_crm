<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJobQueue extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'job_type'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'payload'             => ['type' => 'JSON', 'null' => false],
            'status'              => ['type' => 'ENUM', 'constraint' => ['pending', 'processing', 'done', 'failed'], 'default' => 'pending'],
            'priority'            => ['type' => 'TINYINT', 'default' => 0],
            'locked_until'        => ['type' => 'DATETIME', 'null' => true],
            'run_after'           => ['type' => 'DATETIME', 'null' => true],
            'attempts'            => ['type' => 'TINYINT', 'default' => 0],
            'max_retries'         => ['type' => 'TINYINT', 'default' => 3],
            'error'               => ['type' => 'TEXT', 'null' => true],
            'failed_attempts_log' => ['type' => 'JSON', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'priority', 'run_after']);
        $this->forge->addKey('locked_until');
        $this->forge->createTable('job_queue');
    }

    public function down()
    {
        $this->forge->dropTable('job_queue', true);
    }
}
