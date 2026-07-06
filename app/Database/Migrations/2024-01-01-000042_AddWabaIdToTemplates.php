<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWabaIdToTemplates extends Migration
{
    public function up()
    {
        $this->forge->addColumn('message_templates', [
            'waba_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'account_id',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('message_templates', 'waba_id');
    }
}
