<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsVoiceNoteToMessages extends Migration
{
    public function up()
    {
        $this->forge->addColumn('messages', [
            'is_voice_note' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0, 'after' => 'media_filename'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('messages', 'is_voice_note');
    }
}
